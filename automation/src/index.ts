import { createHash } from 'node:crypto';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium, errors } from 'playwright';
import type { Browser, BrowserContext, Page } from 'playwright';
import { RunnerApiClient } from './api-client.js';
import {
    CredentialConfigurationError,
    parseCredentialValues,
    validateCredentialMetadata,
} from './credentials.js';
import { AssertionFailure, executeDefinition } from './interpreter.js';
import { redact } from './redact.js';
import type {
    ExecutionResult,
    RunnerExecution,
    RunnerPayload,
} from './types.js';
import { validateDefinition } from './validator.js';

const appUrl = requiredEnvironment('TESTIFY_APP_URL');
const signingSecret = requiredEnvironment('TESTIFY_RUNNER_SIGNING_SECRET');
const runId = requiredEnvironment('TESTIFY_AUTOMATION_RUN_ID');
const credentials = parseCredentialValues(
    process.env.TESTIFY_CREDENTIAL_PROFILES_JSON,
);
const secretValues = Object.values(credentials).flatMap((profile) =>
    Object.values(profile),
);
const api = new RunnerApiClient(appUrl, signingSecret);

await main();

async function main(): Promise<void> {
    const payload = await api.payload(runId);
    validateCredentialMetadata(payload.credential_profiles, credentials);
    await api.start(runId, process.env.GITHUB_RUN_ID, githubRunUrl());

    const browser = await chromium.launch({ headless: true });

    try {
        for (const execution of payload.executions) {
            await runExecution(browser, payload, execution);
        }
    } finally {
        await browser.close();
    }
}

async function runExecution(
    browser: Browser,
    payload: RunnerPayload,
    execution: RunnerExecution,
): Promise<void> {
    const startedAt = Date.now();
    let attempt = 0;
    let result: ExecutionResult | undefined;

    while (attempt < 2) {
        attempt += 1;
        result = await runExecutionAttempt(
            browser,
            payload,
            execution,
            startedAt,
        );

        if (
            result.status !== 'Blocked' ||
            result.failure_type !== 'infrastructure' ||
            attempt >= 2
        ) {
            break;
        }
    }

    await api.result(
        payload.id,
        execution.id,
        result ?? {
            status: 'Blocked',
            duration_ms: Date.now() - startedAt,
            failure_type: 'infrastructure',
            failure_message: 'Runner did not produce a result.',
        },
    );
}

async function runExecutionAttempt(
    browser: Browser,
    payload: RunnerPayload,
    execution: RunnerExecution,
    startedAt: number,
): Promise<ExecutionResult> {
    const directory = join(
        tmpdir(),
        `testify-${execution.id}-${createHash('sha1').update(String(Date.now())).digest('hex')}`,
    );
    await mkdir(directory, { recursive: true });
    const tracePath = join(directory, 'trace.zip');
    const screenshotPath = join(directory, 'failure.png');
    const logPath = join(directory, 'console.log');
    const logs: string[] = [];
    let context: BrowserContext | undefined;
    let traceStarted = false;

    try {
        const definition = validateDefinition(
            execution.definition,
            payload.credential_profiles,
        );
        const usesCredentials = definition.steps.some(
            (step) => 'secret' in step && step.secret !== undefined,
        );
        const baseUrl = new URL(payload.base_url);
        context = await browser.newContext({
            baseURL: baseUrl.toString(),
            serviceWorkers: 'block',
        });

        if (!usesCredentials) {
            await context.tracing.start({
                screenshots: true,
                snapshots: true,
                sources: false,
            });
            traceStarted = true;
        }

        const page = await context.newPage();
        page.setDefaultTimeout(payload.case_timeout_seconds * 1000);
        page.on('console', (message) =>
            logs.push(`${message.type()}: ${message.text()}`),
        );
        page.on('pageerror', (error) =>
            logs.push(`pageerror: ${error.message}`),
        );

        await withCaseTimeout(
            executeDefinition(page, definition, baseUrl, credentials),
            payload.case_timeout_seconds * 1000,
        );

        if (traceStarted) {
            await context.tracing.stop({ path: tracePath });
            await uploadIfPresent(
                payload.id,
                execution.id,
                'Trace',
                tracePath,
                'application/zip',
            );
        }

        return {
            status: 'Passed',
            duration_ms: Date.now() - startedAt,
            console_excerpt: sanitizedLogs(logs),
        };
    } catch (error) {
        const message = redact(
            error instanceof Error ? error.message : String(error),
            secretValues,
        );
        const failure = classifyFailure(error, message, Date.now() - startedAt);

        if (context) {
            const page = context.pages().at(-1);

            if (page) {
                await redactPage(page, secretValues);
                await page
                    .screenshot({ path: screenshotPath, fullPage: true })
                    .catch(() => undefined);
            }

            if (traceStarted) {
                await context.tracing
                    .stop({ path: tracePath })
                    .catch(() => undefined);
            }
        }

        await writeFile(
            logPath,
            sanitizedLogs([...logs, `runner: ${message}`]),
            'utf8',
        );
        await uploadIfPresent(
            payload.id,
            execution.id,
            'Screenshot',
            screenshotPath,
            'image/png',
        );

        if (traceStarted) {
            await uploadIfPresent(
                payload.id,
                execution.id,
                'Trace',
                tracePath,
                'application/zip',
            );
        }

        await uploadIfPresent(
            payload.id,
            execution.id,
            'Log',
            logPath,
            'text/plain',
        );

        return { ...failure, console_excerpt: sanitizedLogs(logs) };
    } finally {
        await context?.close().catch(() => undefined);
        await rm(directory, { recursive: true, force: true });
    }
}

function classifyFailure(
    error: unknown,
    message: string,
    durationMs: number,
): ExecutionResult {
    if (error instanceof AssertionFailure) {
        return {
            status: 'Failed',
            duration_ms: durationMs,
            failure_type: 'assertion',
            failure_message: message,
            failed_step: error.stepNumber,
        };
    }

    if (error instanceof CredentialConfigurationError) {
        return {
            status: 'Blocked',
            duration_ms: durationMs,
            failure_type: 'credential',
            failure_message: message,
        };
    }

    if (
        error instanceof errors.TimeoutError ||
        message === 'The case exceeded its configured timeout.'
    ) {
        return {
            status: 'Blocked',
            duration_ms: durationMs,
            failure_type: 'infrastructure',
            failure_message: message,
        };
    }

    return {
        status: 'Blocked',
        duration_ms: durationMs,
        failure_type: 'infrastructure',
        failure_message: message,
    };
}

async function uploadIfPresent(
    automationRunId: string,
    executionId: string,
    type: 'Screenshot' | 'Trace' | 'Log',
    path: string,
    mimeType: string,
): Promise<void> {
    await api
        .uploadArtifact(automationRunId, executionId, type, path, mimeType)
        .catch((error: unknown) => {
            process.stderr.write(
                `${redact(error instanceof Error ? error.message : String(error), secretValues)}\n`,
            );
        });
}

function sanitizedLogs(logs: string[]): string {
    return redact(logs.join('\n').slice(-8_000), secretValues);
}

async function redactPage(page: Page, secrets: string[]): Promise<void> {
    const values = secrets.filter((secret) => secret.length > 0);

    if (values.length === 0) {
        return;
    }

    await page
        .locator('input, textarea')
        .evaluateAll((elements, sensitiveValues) => {
            for (const element of elements) {
                if (
                    element instanceof HTMLInputElement ||
                    element instanceof HTMLTextAreaElement
                ) {
                    for (const sensitiveValue of sensitiveValues) {
                        element.value = element.value.replaceAll(
                            sensitiveValue,
                            '[REDACTED]',
                        );
                    }
                }
            }
        }, values)
        .catch(() => undefined);

    await page
        .locator('body')
        .evaluate((body, sensitiveValues) => {
            const walker = document.createTreeWalker(
                body,
                NodeFilter.SHOW_TEXT,
            );
            let node = walker.nextNode();

            while (node) {
                for (const sensitiveValue of sensitiveValues) {
                    node.textContent = (node.textContent ?? '').replaceAll(
                        sensitiveValue,
                        '[REDACTED]',
                    );
                }

                node = walker.nextNode();
            }
        }, values)
        .catch(() => undefined);
}

async function withCaseTimeout<T>(
    operation: Promise<T>,
    milliseconds: number,
): Promise<T> {
    let timeout: NodeJS.Timeout | undefined;

    try {
        return await Promise.race([
            operation,
            new Promise<never>((_, reject) => {
                timeout = setTimeout(
                    () =>
                        reject(
                            new Error(
                                'The case exceeded its configured timeout.',
                            ),
                        ),
                    milliseconds,
                );
            }),
        ]);
    } finally {
        if (timeout) {
            clearTimeout(timeout);
        }
    }
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
    const id = process.env.GITHUB_RUN_ID;

    return server && repository && id
        ? `${server}/${repository}/actions/runs/${id}`
        : undefined;
}
