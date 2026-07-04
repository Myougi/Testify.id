import { createHash, createHmac, randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import type { ExecutionResult, RunnerPayload } from './types.js';

interface ReservedArtifact {
    id: number;
    upload_url: string;
    headers: Record<string, string>;
}

export class RunnerApiClient {
    private readonly baseUrl: URL;

    public constructor(
        baseUrl: string,
        private readonly sharedSecret: string,
    ) {
        this.baseUrl = new URL(baseUrl);
    }

    public async payload(runId: string): Promise<RunnerPayload> {
        return this.request<{ data: RunnerPayload }>(
            'GET',
            `/api/runner/v1/automation-runs/${runId}`,
        ).then((response) => response.data);
    }

    public async start(
        runId: string,
        providerRunId?: string,
        providerRunUrl?: string,
    ): Promise<void> {
        await this.request(
            'POST',
            `/api/runner/v1/automation-runs/${runId}/start`,
            {
                provider_run_id: providerRunId,
                provider_run_url: providerRunUrl,
            },
        );
    }

    public async result(
        runId: string,
        executionId: string,
        result: ExecutionResult,
    ): Promise<void> {
        await this.request(
            'POST',
            `/api/runner/v1/automation-runs/${runId}/executions/${executionId}/result`,
            result,
        );
    }

    public async uploadArtifact(
        runId: string,
        executionId: string,
        type: 'Screenshot' | 'Trace' | 'Log' | 'Diagnostic',
        path: string,
        mimeType: string,
    ): Promise<void> {
        const contents = await readFile(path);
        const reserved = await this.request<{ data: ReservedArtifact }>(
            'POST',
            `/api/runner/v1/automation-runs/${runId}/executions/${executionId}/artifacts`,
            {
                type,
                original_name: path.split(/[\\/]/).at(-1),
                mime_type: mimeType,
                size: contents.byteLength,
                sha256: createHash('sha256').update(contents).digest('hex'),
            },
        );
        const upload = await fetch(reserved.data.upload_url, {
            method: 'PUT',
            headers: { ...reserved.data.headers, 'Content-Type': mimeType },
            body: new Blob([Uint8Array.from(contents)]),
        });

        if (!upload.ok) {
            throw new Error(
                `Artifact upload failed with status ${upload.status}.`,
            );
        }

        await this.request(
            'POST',
            `/api/runner/v1/automation-runs/${runId}/executions/${executionId}/artifacts/${reserved.data.id}/finalize`,
            {},
        );
    }

    private async request<T = unknown>(
        method: string,
        path: string,
        data?: object,
    ): Promise<T> {
        const body = data === undefined ? '' : JSON.stringify(data);
        const timestamp = Math.floor(Date.now() / 1000).toString();
        const requestId = randomUUID();
        const canonical = [
            timestamp,
            method,
            path,
            createHash('sha256').update(body).digest('hex'),
        ].join('\n');
        const signature = createHmac('sha256', this.sharedSecret)
            .update(canonical)
            .digest('hex');
        const request: RequestInit = {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'Idempotency-Key': requestId,
                'X-Testify-Request-Id': requestId,
                'X-Testify-Timestamp': timestamp,
                'X-Testify-Signature': signature,
            },
        };

        if (body !== '') {
            request.body = body;
        }

        const response = await fetch(new URL(path, this.baseUrl), request);

        if (!response.ok) {
            throw new Error(
                `Runner API ${method} ${path} failed with status ${response.status}: ${await response.text()}`,
            );
        }

        return response.json() as Promise<T>;
    }
}
