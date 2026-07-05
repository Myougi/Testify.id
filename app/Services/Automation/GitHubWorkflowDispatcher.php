<?php

namespace App\Services\Automation;

use App\Models\AutomationRun;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubWorkflowDispatcher
{
    public function dispatch(AutomationRun $automationRun): void
    {
        $owner = trim((string) config('automation.github.owner'));
        $repository = trim((string) config('automation.github.repository'));
        $workflow = trim((string) ($automationRun->kind === AutomationRun::KIND_GENERATION
            ? config('automation.agentic_generation.workflow')
            : config('automation.github.workflow')));
        $ref = trim((string) config('automation.github.ref'));
        $token = trim((string) config('automation.github.token'));

        if ($owner === '' || $repository === '' || $workflow === '' || $ref === '' || $token === '') {
            throw new RuntimeException('GitHub Actions automation is not fully configured.');
        }

        $baseUrl = rtrim((string) config('automation.github.api_url', 'https://api.github.com'), '/');
        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(20)
            ->post("{$baseUrl}/repos/{$owner}/{$repository}/actions/workflows/{$workflow}/dispatches", [
                'ref' => $ref,
                'inputs' => [
                    'automation_run_id' => $automationRun->id,
                    'environment' => $automationRun->environment,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GitHub workflow dispatch failed with status '.$response->status().'.');
        }
    }
}
