<?php

namespace App\Services\Automation;

use App\Jobs\DispatchAutomationRun;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\ProjectEnvironment;
use App\Models\TestCase;
use App\Models\TestCaseAutomation;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomationDispatchService
{
    public function __construct(private readonly AutomationDefinitionService $definitions) {}

    public function enabled(): bool
    {
        return (bool) config('automation.enabled', false);
    }

    public function assertCanStart(TestRun $testRun): void
    {
        if (! $this->enabled()) {
            return;
        }

        $testRun->loadMissing(['testPlan.project.environments', 'testCases.automation', 'testCases.steps']);
        $automatedCases = $this->automatedCases($testRun);

        if ($automatedCases->isEmpty()) {
            return;
        }

        $this->assertConfigured();
        $this->environmentFor($testRun);

        if ($automatedCases->count() > (int) config('automation.limits.cases_per_run', 25)) {
            throw ValidationException::withMessages([
                'automation' => 'A test run may contain at most '.config('automation.limits.cases_per_run', 25).' automated cases.',
            ]);
        }

        $unready = $automatedCases
            ->reject(fn (TestCase $testCase): bool => $this->definitions->isReady($testCase))
            ->pluck('case_key')
            ->values()
            ->all();

        if ($unready !== []) {
            throw ValidationException::withMessages([
                'automation' => 'Automated cases require a current Ready definition: '.implode(', ', $unready).'.',
            ]);
        }

        foreach ($automatedCases as $testCase) {
            $this->definitions->validateForEnvironment($testCase->automation, $testRun->environment);
        }
    }

    public function dispatchForTestRun(TestRun $testRun): ?AutomationRun
    {
        if (! $this->enabled()) {
            return null;
        }

        $testRun->loadMissing(['testPlan.project.environments', 'testCases.automation', 'testCases.steps']);
        $cases = $this->automatedCases($testRun);

        if ($cases->isEmpty()) {
            return null;
        }

        return $this->createRun(
            kind: AutomationRun::KIND_EXECUTION,
            testRun: $testRun,
            cases: $cases,
            environment: $testRun->environment,
            baseUrl: $this->environmentFor($testRun)->base_url,
            user: $testRun->testPlan->project->user,
        );
    }

    public function dispatchValidation(TestCaseAutomation $automation, string $environment, User $user): AutomationRun
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['automation' => 'Browser automation is not enabled.']);
        }

        if ($environment === TestRun::ENVIRONMENT_PRODUCTION) {
            throw ValidationException::withMessages([
                'environment' => 'Automation definitions must be validated in a non-production environment.',
            ]);
        }

        $this->assertConfigured();
        $automation->loadMissing(['testCase.project.environments', 'testCase.steps']);
        $this->definitions->validateForEnvironment($automation, $environment);
        $projectEnvironment = $automation->testCase->project->environments
            ->first(fn (ProjectEnvironment $candidate): bool => $candidate->environment === $environment && $candidate->enabled);

        if (! $projectEnvironment) {
            throw ValidationException::withMessages([
                'environment' => "No enabled {$environment} URL is configured for this project.",
            ]);
        }

        $automation->forceFill([
            'status' => TestCaseAutomation::STATUS_VALIDATING,
            'approved_by' => null,
            'approval_source' => null,
            'approved_at' => null,
        ])->save();

        /** @var Collection<int, TestCase> $cases */
        $cases = new Collection([$automation->testCase]);

        return $this->createRun(
            kind: AutomationRun::KIND_VALIDATION,
            testRun: null,
            cases: $cases,
            environment: $environment,
            baseUrl: $projectEnvironment->base_url,
            user: $user,
        );
    }

    public function retryFailed(TestRun $testRun, User $user): AutomationRun
    {
        $latest = $testRun->automationRuns()
            ->where('kind', AutomationRun::KIND_EXECUTION)
            ->latest('attempt')
            ->with(['executions.testCase.automation', 'executions.testCase.steps'])
            ->firstOrFail();
        $cases = $latest->executions
            ->whereIn('status', [AutomationExecution::STATUS_FAILED, AutomationExecution::STATUS_BLOCKED])
            ->map(fn (AutomationExecution $execution): TestCase => $execution->testCase)
            ->values();

        if ($cases->isEmpty()) {
            throw ValidationException::withMessages(['automation' => 'There are no failed or blocked automated cases to retry.']);
        }

        foreach ($cases as $testCase) {
            if (! $this->definitions->isReady($testCase)) {
                throw ValidationException::withMessages([
                    'automation' => "{$testCase->case_key} no longer has a current Ready definition.",
                ]);
            }
        }

        $environment = $this->environmentFor($testRun);

        return $this->createRun(
            kind: AutomationRun::KIND_EXECUTION,
            testRun: $testRun,
            cases: $cases,
            environment: $testRun->environment,
            baseUrl: $environment->base_url,
            user: $user,
        );
    }

    /**
     * @param  Collection<int, TestCase>  $cases
     */
    private function createRun(
        string $kind,
        ?TestRun $testRun,
        Collection $cases,
        string $environment,
        string $baseUrl,
        User $user,
    ): AutomationRun {
        return DB::transaction(function () use ($kind, $testRun, $cases, $environment, $baseUrl, $user): AutomationRun {
            $attempt = $testRun
                ? ((int) $testRun->automationRuns()->lockForUpdate()->max('attempt')) + 1
                : 1;
            $run = AutomationRun::query()->create([
                'kind' => $kind,
                'test_run_id' => $testRun?->id,
                'requested_by' => $user->id,
                'environment' => $environment,
                'base_url' => rtrim($baseUrl, '/'),
                'attempt' => $attempt,
                'provider' => 'github_actions',
                'status' => AutomationRun::STATUS_QUEUED,
            ]);

            foreach ($cases as $testCase) {
                $automation = $testCase->automation;

                if (! $automation || ! is_array($automation->definition)) {
                    throw ValidationException::withMessages([
                        'automation' => "{$testCase->case_key} does not have an automation definition.",
                    ]);
                }

                $run->executions()->create([
                    'test_case_id' => $testCase->id,
                    'test_case_automation_id' => $automation->id,
                    'definition_snapshot' => $automation->definition,
                    'status' => AutomationExecution::STATUS_QUEUED,
                ]);
            }

            DispatchAutomationRun::dispatch($run->id)->afterCommit();

            return $run->load(['executions.testCase']);
        });
    }

    /** @return Collection<int, TestCase> */
    private function automatedCases(TestRun $testRun): Collection
    {
        return $testRun->testCases
            ->filter(fn (TestCase $testCase): bool => $testCase->automation_status === TestCase::AUTOMATION_AUTOMATED)
            ->values();
    }

    private function environmentFor(TestRun $testRun): ProjectEnvironment
    {
        $testRun->loadMissing('testPlan.project.environments');
        $environment = $testRun->testPlan->project->environments
            ->first(fn (ProjectEnvironment $candidate): bool => $candidate->environment === $testRun->environment && $candidate->enabled);

        if (! $environment) {
            throw ValidationException::withMessages([
                'environment' => "No enabled {$testRun->environment} URL is configured for this project.",
            ]);
        }

        return $environment;
    }

    private function assertConfigured(): void
    {
        foreach (['owner', 'repository', 'workflow', 'ref', 'token'] as $key) {
            if (trim((string) config("automation.github.{$key}")) === '') {
                throw ValidationException::withMessages(['automation' => 'GitHub Actions automation is not fully configured.']);
            }
        }

        if (trim((string) config('automation.runner.shared_secret')) === '') {
            throw ValidationException::withMessages(['automation' => 'The automation runner signing secret is not configured.']);
        }
    }
}
