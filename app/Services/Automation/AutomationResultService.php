<?php

namespace App\Services\Automation;

use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\ProjectCredentialProfile;
use App\Models\TestRun;
use App\Services\TestRunService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AutomationResultService
{
    /** @var list<string> */
    private const FINAL_STATUSES = [
        AutomationExecution::STATUS_PASSED,
        AutomationExecution::STATUS_FAILED,
        AutomationExecution::STATUS_BLOCKED,
        AutomationExecution::STATUS_SKIPPED,
    ];

    public function __construct(
        private readonly AutomationDefinitionService $definitions,
        private readonly TestRunService $testRuns,
    ) {}

    /** @return array<string, mixed> */
    public function payload(AutomationRun $run): array
    {
        $run->loadMissing(['testRun.testPlan.project', 'executions.testCase.project']);
        $project = $this->projectFor($run);
        $profiles = ProjectCredentialProfile::query()
            ->where('project_id', $project->project_id)
            ->where('environment', $run->environment)
            ->get()
            ->map(fn (ProjectCredentialProfile $profile): array => [
                'name' => $profile->name,
                'key' => $profile->key,
                'fields' => $profile->fields,
            ])->values()->all();

        return [
            'id' => $run->id,
            'kind' => $run->kind,
            'environment' => $run->environment,
            'base_url' => $run->base_url,
            'case_timeout_seconds' => (int) config('automation.limits.case_timeout_seconds', 120),
            'credential_profiles' => $profiles,
            'executions' => $run->executions->map(fn (AutomationExecution $execution): array => [
                'id' => $execution->id,
                'test_case_id' => $execution->test_case_id,
                'case_key' => $execution->testCase->case_key,
                'title' => $execution->testCase->title,
                'definition' => $execution->definition_snapshot,
            ])->values()->all(),
        ];
    }

    public function start(AutomationRun $run, ?string $providerRunId, ?string $providerRunUrl): AutomationRun
    {
        if (in_array($run->status, [AutomationRun::STATUS_RUNNING, AutomationRun::STATUS_COMPLETED, AutomationRun::STATUS_FAILED], true)) {
            return $run;
        }

        DB::transaction(function () use ($run, $providerRunId, $providerRunUrl): void {
            $run->forceFill([
                'status' => AutomationRun::STATUS_RUNNING,
                'provider_run_id' => $providerRunId,
                'provider_run_url' => $providerRunUrl,
                'started_at' => $run->started_at ?? now(),
            ])->save();
            $run->executions()->where('status', AutomationExecution::STATUS_QUEUED)->update([
                'status' => AutomationExecution::STATUS_RUNNING,
                'started_at' => now(),
                'updated_at' => now(),
            ]);

            if ($run->kind === AutomationRun::KIND_EXECUTION && $run->test_run_id !== null) {
                foreach ($run->executions as $execution) {
                    DB::table('test_case_test_run')
                        ->where('test_run_id', $run->test_run_id)
                        ->where('test_case_id', $execution->test_case_id)
                        ->update(['execution_status' => TestRun::CASE_STATUS_IN_TESTING, 'updated_at' => now()]);
                }
            }
        });

        return $run->fresh(['executions']);
    }

    /** @param array<string, mixed> $result */
    public function completeExecution(AutomationExecution $execution, array $result): AutomationExecution
    {
        $status = (string) ($result['status'] ?? '');

        if (! in_array($status, self::FINAL_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'The execution result status is invalid.']);
        }

        if (in_array($execution->status, self::FINAL_STATUSES, true)) {
            if ($execution->status !== $status) {
                throw ValidationException::withMessages(['status' => 'A completed execution result cannot be changed.']);
            }

            return $execution;
        }

        return DB::transaction(function () use ($execution, $result, $status): AutomationExecution {
            $execution->forceFill([
                'status' => $status,
                'duration_ms' => isset($result['duration_ms']) ? max(0, (int) $result['duration_ms']) : null,
                'failure_type' => $this->nullableString($result['failure_type'] ?? null, 40),
                'failure_message' => $this->nullableString($result['failure_message'] ?? null, 10000),
                'failed_step' => isset($result['failed_step']) ? max(1, (int) $result['failed_step']) : null,
                'console_excerpt' => $this->nullableString(
                    $result['console_excerpt'] ?? null,
                    (int) config('automation.limits.console_excerpt_length', 8000),
                ),
                'started_at' => $execution->started_at ?? now(),
                'completed_at' => now(),
            ])->save();

            $run = $execution->automationRun()->with(['executions', 'testRun'])->firstOrFail();

            if ($run->kind === AutomationRun::KIND_VALIDATION && $execution->test_case_automation_id !== null) {
                $this->definitions->markValidationResult(
                    $execution->automation()->firstOrFail(),
                    $run->environment,
                    $status === AutomationExecution::STATUS_PASSED,
                    $execution->failure_message ?? 'Browser validation passed.',
                );
            }

            if ($run->kind === AutomationRun::KIND_EXECUTION && $run->testRun) {
                DB::table('test_case_test_run')
                    ->where('test_run_id', $run->test_run_id)
                    ->where('test_case_id', $execution->test_case_id)
                    ->update([
                        'execution_status' => $this->testRunStatus($status),
                        'updated_at' => now(),
                    ]);
                $run->testRun->increment('api_version');
                $this->testRuns->refreshLifecycleStatus($run->testRun);
            }

            $this->finalizeRunWhenComplete($run);

            return $execution->fresh(['artifacts']);
        });
    }

    /**
     * @param  array{type: string, original_name: string, mime_type: string, size: int, sha256: string}  $data
     * @return array{artifact: AutomationArtifact, upload_url: string, headers: array<string, string>}
     */
    public function reserveArtifact(AutomationExecution $execution, array $data): array
    {
        $types = [AutomationArtifact::TYPE_SCREENSHOT, AutomationArtifact::TYPE_TRACE, AutomationArtifact::TYPE_LOG];
        $mimes = config('automation.artifacts.allowed_mime_types', []);
        $maxBytes = (int) config('automation.limits.artifact_size_kilobytes', 15360) * 1024;

        if (! in_array($data['type'], $types, true)
            || ! in_array($data['mime_type'], $mimes, true)
            || $data['size'] < 1 || $data['size'] > $maxBytes
            || preg_match('/^[a-f0-9]{64}$/', $data['sha256']) !== 1) {
            throw ValidationException::withMessages(['artifact' => 'The automation artifact metadata is invalid.']);
        }

        $disk = (string) config('filesystems.automation_artifacts_disk', 's3');
        $filename = Str::uuid().'-'.Str::of($data['original_name'])->basename()->replaceMatches('/[^A-Za-z0-9._-]/', '-');
        $path = "automation/{$execution->automation_run_id}/{$execution->id}/{$filename}";
        ['url' => $url, 'headers' => $headers] = Storage::disk($disk)->temporaryUploadUrl(
            $path,
            now()->addMinutes(10),
            ['ContentType' => $data['mime_type']],
        );
        $artifact = $execution->artifacts()->create([
            ...$data,
            'disk' => $disk,
            'path' => $path,
        ]);

        return ['artifact' => $artifact, 'upload_url' => $url, 'headers' => $headers];
    }

    public function finalizeArtifact(AutomationExecution $execution, AutomationArtifact $artifact): AutomationArtifact
    {
        if ($artifact->automation_execution_id !== $execution->id) {
            abort(404);
        }

        if ($artifact->uploaded_at !== null) {
            return $artifact;
        }

        $storage = Storage::disk($artifact->disk);

        if (! $storage->exists($artifact->path) || $storage->size($artifact->path) !== $artifact->size) {
            throw ValidationException::withMessages(['artifact' => 'The uploaded artifact size does not match its reservation.']);
        }

        $stream = $storage->readStream($artifact->path);

        if ($stream === null) {
            throw ValidationException::withMessages(['artifact' => 'The uploaded artifact could not be read.']);
        }

        try {
            $actualHash = hash_init('sha256');
            hash_update_stream($actualHash, $stream);
            $actualHash = hash_final($actualHash);
        } finally {
            fclose($stream);
        }

        if (! hash_equals($artifact->sha256, $actualHash)) {
            $storage->delete($artifact->path);
            $artifact->delete();

            throw ValidationException::withMessages(['artifact' => 'The uploaded artifact checksum does not match its reservation.']);
        }

        $artifact->forceFill(['uploaded_at' => now()])->save();

        return $artifact;
    }

    public function failDispatch(?AutomationRun $run, string $message): void
    {
        if (! $run || in_array($run->status, [AutomationRun::STATUS_COMPLETED, AutomationRun::STATUS_FAILED], true)) {
            return;
        }

        DB::transaction(function () use ($run, $message): void {
            $run->forceFill([
                'status' => AutomationRun::STATUS_ERROR,
                'failure_message' => Str::limit($message, 10000, ''),
                'completed_at' => now(),
            ])->save();

            foreach ($run->executions as $execution) {
                if (in_array($execution->status, self::FINAL_STATUSES, true)) {
                    continue;
                }

                $this->completeExecution($execution, [
                    'status' => AutomationExecution::STATUS_BLOCKED,
                    'failure_type' => 'infrastructure',
                    'failure_message' => $message,
                ]);
            }

            $run->forceFill([
                'status' => AutomationRun::STATUS_ERROR,
                'failure_message' => Str::limit($message, 10000, ''),
                'completed_at' => now(),
            ])->save();
        });
    }

    private function finalizeRunWhenComplete(AutomationRun $run): void
    {
        $run->load('executions');

        if ($run->executions->contains(fn (AutomationExecution $execution): bool => ! in_array($execution->status, self::FINAL_STATUSES, true))) {
            return;
        }

        $allPassed = $run->executions->every(
            fn (AutomationExecution $execution): bool => $execution->status === AutomationExecution::STATUS_PASSED,
        );
        $run->forceFill([
            'status' => $allPassed ? AutomationRun::STATUS_COMPLETED : AutomationRun::STATUS_FAILED,
            'completed_at' => now(),
        ])->save();
    }

    private function testRunStatus(string $status): string
    {
        return match ($status) {
            AutomationExecution::STATUS_PASSED => TestRun::CASE_STATUS_PASSED,
            AutomationExecution::STATUS_FAILED => TestRun::CASE_STATUS_FAILED,
            AutomationExecution::STATUS_SKIPPED => TestRun::CASE_STATUS_SKIPPED,
            default => TestRun::CASE_STATUS_BLOCKED,
        };
    }

    private function projectFor(AutomationRun $run): Project
    {
        $project = $run->testRun !== null
            ? $run->testRun->testPlan->project
            : $run->executions->first()?->testCase->project;

        if (! $project instanceof Project) {
            throw new RuntimeException('The automation run does not belong to a project.');
        }

        return $project;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : Str::limit($value, $maxLength, '');
    }
}
