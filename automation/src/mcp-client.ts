import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import {
    getDefaultEnvironment,
    StdioClientTransport,
} from '@modelcontextprotocol/sdk/client/stdio.js';

const allowedTools = new Set([
    'browser_navigate',
    'browser_snapshot',
    'browser_click',
    'browser_type',
    'browser_select_option',
    'browser_press_key',
    'browser_wait_for',
]);

export interface BrowserToolClient {
    start(): Promise<void>;
    call(name: string, arguments_: Record<string, unknown>): Promise<string>;
    close(): Promise<void>;
}

export class PlaywrightMcpClient implements BrowserToolClient {
    private readonly client = new Client({
        name: 'testify-agentic-generator',
        version: '1.0.0',
    });

    private readonly transport = new StdioClientTransport({
        command: process.execPath,
        args: [
            resolve(
                dirname(fileURLToPath(import.meta.url)),
                '../../node_modules/@playwright/mcp/cli.js',
            ),
            '--headless',
            '--isolated',
            '--browser',
            'chromium',
            '--block-service-workers',
            '--snapshot-mode',
            'full',
        ],
        cwd: process.cwd(),
        env: getDefaultEnvironment(),
        stderr: 'inherit',
    });

    public async start(): Promise<void> {
        await this.client.connect(this.transport);
        const tools = await this.client.listTools();
        const available = new Set(tools.tools.map((tool) => tool.name));
        const missing = [...allowedTools].filter((tool) => !available.has(tool));

        if (missing.length > 0) {
            throw new Error(`Playwright MCP is missing required tools: ${missing.join(', ')}.`);
        }
    }

    public async call(
        name: string,
        arguments_: Record<string, unknown>,
    ): Promise<string> {
        if (!allowedTools.has(name)) {
            throw new Error(`MCP tool ${name} is not allowed.`);
        }

        const result = await this.client.callTool({
            name,
            arguments: arguments_,
        });

        if (result.isError) {
            throw new Error(`MCP tool ${name} failed: ${contentText(result.content)}.`);
        }

        return contentText(result.content);
    }

    public async close(): Promise<void> {
        await this.transport.close();
    }
}

function contentText(content: unknown): string {
    if (!Array.isArray(content)) {
        return '';
    }

    return content
        .filter(
            (item): item is { type: 'text'; text: string } =>
                typeof item === 'object' &&
                item !== null &&
                'type' in item &&
                item.type === 'text' &&
                'text' in item &&
                typeof item.text === 'string',
        )
        .map((item) => item.text)
        .join('\n');
}
