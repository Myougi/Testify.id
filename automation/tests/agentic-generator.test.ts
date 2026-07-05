import assert from 'node:assert/strict';
import test from 'node:test';
import {
    AgenticGenerationError,
    runAgenticGeneration,
} from '../src/agentic-generator.js';
import type { BrowserToolClient } from '../src/mcp-client.js';
import type {
    AgentDecision,
    AgentDecisionModel,
} from '../src/openrouter.js';
import type { RunnerPayload } from '../src/types.js';

test('agentic generation records stable DSL and never sends credential values to the model', async () => {
    const browser = new FakeBrowser([
        snapshot('https://sit.example.test/'),
        snapshot('https://sit.example.test/dashboard', 'secret-value'),
    ]);
    const model = new FakeModel([
        decision({
            tool: 'browser_type',
            target: 'e5',
            dsl_step: generatedStep({
                action: 'fill',
                locator_by: 'label',
                locator_value: 'Username',
                secret_profile: 'standard_user',
                secret_field: 'username',
            }),
        }),
        decision({ status: 'complete', tool: null, dsl_step: null }),
    ]);
    const payload = generationPayload();
    const output = await runAgenticGeneration(
        payload,
        payload.executions[0]!,
        { standard_user: { username: 'secret-value' } },
        browser,
        model,
    );

    assert.equal(output.definition.steps[1]?.action, 'fill');
    assert.equal(JSON.stringify(output.definition).includes('e5'), false);
    assert.equal(model.prompts.some((prompt) => prompt.includes('secret-value')), false);
    assert.equal(
        browser.calls.some(
            (call) =>
                call.name === 'browser_type' &&
                call.arguments.text === 'secret-value',
        ),
        true,
    );
});

test('agentic generation blocks cross-origin navigation after an action', async () => {
    const browser = new FakeBrowser([
        snapshot('https://sit.example.test/'),
        snapshot('https://attacker.example/'),
    ]);
    const model = new FakeModel([
        decision({
            tool: 'browser_click',
            target: 'e8',
            dsl_step: {
                action: 'click',
                locator: { by: 'role', role: 'button', name: 'Continue' },
            },
        }),
    ]);
    const payload = generationPayload();

    await assert.rejects(
        runAgenticGeneration(
            payload,
            payload.executions[0]!,
            {},
            browser,
            model,
        ),
        (error: unknown) =>
            error instanceof AgenticGenerationError &&
            error.code === 'external_navigation_blocked',
    );
});

test('agentic generation rejects snapshot references inside durable locators', async () => {
    const browser = new FakeBrowser([snapshot('https://sit.example.test/')]);
    const model = new FakeModel([
        decision({
            tool: 'browser_click',
            target: 'e8',
            dsl_step: {
                action: 'click',
                locator: { by: 'css', value: 'e8' },
            },
        }),
    ]);
    const payload = generationPayload();

    await assert.rejects(
        runAgenticGeneration(
            payload,
            payload.executions[0]!,
            {},
            browser,
            model,
        ),
        (error: unknown) =>
            error instanceof AgenticGenerationError &&
            error.code === 'unstable_locator',
    );
});

class FakeBrowser implements BrowserToolClient {
    public readonly calls: Array<{
        name: string;
        arguments: Record<string, unknown>;
    }> = [];

    public constructor(private readonly snapshots: string[]) {}

    public async start(): Promise<void> {}

    public async call(
        name: string,
        arguments_: Record<string, unknown>,
    ): Promise<string> {
        this.calls.push({ name, arguments: arguments_ });

        if (name === 'browser_snapshot') {
            const next = this.snapshots.shift();

            if (!next) {
                throw new Error('No fake snapshot remains.');
            }

            return next;
        }

        return 'ok';
    }

    public async close(): Promise<void> {}
}

class FakeModel implements AgentDecisionModel {
    public readonly prompts: string[] = [];

    public constructor(private readonly decisions: AgentDecision[]) {}

    public async decide(prompt: string): Promise<AgentDecision> {
        this.prompts.push(prompt);
        const next = this.decisions.shift();

        if (!next) {
            throw new Error('No fake decision remains.');
        }

        return next;
    }
}

function decision(overrides: Partial<AgentDecision>): AgentDecision {
    return {
        status: 'continue',
        manual_step: 1,
        rationale: 'Follow the saved manual Test Case step.',
        tool: 'browser_snapshot',
        target: null,
        text: null,
        values: null,
        key: null,
        seconds: null,
        dsl_step: {
            action: 'assert_visible',
            locator: { by: 'role', role: 'heading', name: 'Dashboard' },
        },
        warning: null,
        ...overrides,
    };
}

function generatedStep(
    overrides: Record<string, unknown>,
): Record<string, unknown> {
    return {
        action: 'click',
        path: null,
        locator_by: null,
        locator_role: null,
        locator_name: null,
        locator_value: null,
        literal_value: null,
        secret_profile: null,
        secret_field: null,
        key: null,
        milliseconds: null,
        ...overrides,
    };
}

function generationPayload(): RunnerPayload {
    return {
        id: '019f0000-0000-7000-8000-000000000001',
        kind: 'Generation',
        environment: 'SIT',
        base_url: 'https://sit.example.test',
        case_timeout_seconds: 120,
        diagnostic_max_characters: 20000,
        generation: {
            goal_prompt: 'Log in.',
            model: 'test/model',
            max_turns: 5,
            timeout_seconds: 900,
            snapshot_max_characters: 20000,
            source_hash: 'source-hash',
        },
        credential_profiles: [
            {
                name: 'Standard user',
                key: 'standard_user',
                fields: ['username'],
            },
        ],
        executions: [
            {
                id: '019f0000-0000-7000-8000-000000000002',
                test_case_id: 1,
                case_key: 'TC-1',
                title: 'Log in',
                definition: null,
                preconditions: 'A user exists.',
                manual_steps: [
                    {
                        position: 1,
                        action: 'Enter valid credentials.',
                        expected_result: 'Dashboard is displayed.',
                    },
                ],
            },
        ],
    };
}

function snapshot(url: string, content = ''): string {
    return `- Page URL: ${url}\n- heading "Page"\n${content}`;
}
