import { mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { runAgenticGeneration } from './agentic-generator.js';
import { RunnerApiClient } from './api-client.js';
import {
    parseCredentialValues,
    validateCredentialMetadata,
} from './credentials.js';
import { PlaywrightMcpClient } from './mcp-client.js';
import { OpenRouterAgentModel } from './openrouter.js';
import { redact } from './redact.js';

const appUrl = requiredEnvironment('TESTIFY_APP_URL');
const signingSecret = requiredEnvironment('TESTIFY_RUNNER_SIGNING_SECRET');
const runId = requiredEnvironment('TESTIFY_AUTOMATION_RUN_ID');
const openRouterKey = requiredEnvironment('OPENROUTER_API_KEY');
const credentials = parseCredentialValues(
    process.env.TESTIFY_CREDENTIAL_PROFILES_JSON,
);
const secretValues = Object.values(credentials).flatMap((profile) =>
    Object.values(profile),
);
const api = new RunnerApiClient(appUrl, signingSecret);

await main();

async function main(): Promise<void> {
    const startedAt = Date.now();
    const payload = await api.payload(runId);
    const execution = payload.executions[0];

    if (payload.kind !== 'Generation' || !payload.generation || !execution) {
        throw new Error('Runner payload does not contain one generation execution.');
    }

    validateCredentialMetadata(payload.credential_profiles, credentials);
    await api.start(runId, process.env.GITHUB_RUN_ID, githubRunUrl());
    await api.progress(runId, 'Starting browser exploration');

    try {
        const output = await runAgenticGeneration(
            payload,
            execution,
            credentials,
            new PlaywrightMcpClient(),
            new OpenRouterAgentModel(
                openRouterKey,
                payload.generation.model,
                process.env.OPENROUTER_API_URL,
            ),
            (message) =>
                api.progress(runId, 'Exploring browser', message),
        );
        await api.progress(
            runId,
            'Compiling recorded actions',
            'Laravel is validating the browser-recorded definition.',
        );
        await api.generationResult(runId, execution.id, {
            success: true,
            definition: output.definition,
            warnings: output.warnings,
            duration_ms: Date.now() - startedAt,
        });
    } catch (error) {
        const code =
            error instanceof Error && 'code' in error
                ? String(error.code)
                : 'agentic_generation_failed';
        const message = redact(
            error instanceof Error ? error.message : String(error),
            secretValues,
        );
        await uploadDiagnostic(execution.id, code, message);
        await api.generationResult(runId, execution.id, {
            success: false,
            warnings: [],
            failure_code: code,
            failure_message: message,
            duration_ms: Date.now() - startedAt,
        });
        process.exitCode = 1;
    }
}

async function uploadDiagnostic(
    executionId: string,
    code: string,
    message: string,
): Promise<void> {
    const directory = join(tmpdir(), `testify-generation-${executionId}`);
    const path = join(directory, 'generation-diagnostic.json');
    await mkdir(directory, { recursive: true });
    await writeFile(
        path,
        JSON.stringify({ failure_code: code, failure_message: message }, null, 2),
        'utf8',
    );
    await api
        .uploadArtifact(runId, executionId, 'Diagnostic', path, 'application/json')
        .catch(() => undefined);
}

function requiredEnvironment(name: string): string {
    const value = process.env[name]?.trim();

    if (!value) {
        throw new Error(`${name} is required.`);
    }

    return value;
}

function githubRunUrl(): string | undefined {
    const server = process.env.GITHUB_SERVER_URL;
    const repository = process.env.GITHUB_REPOSITORY;
    const run = process.env.GITHUB_RUN_ID;

    return server && repository && run
        ? `${server}/${repository}/actions/runs/${run}`
        : undefined;
}
