<?php

namespace App\Http\Resources;

use App\Models\TestRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin TestRun
 */
class TestRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'environment' => $this->environment,
            'priority' => $this->priority,
            'description' => $this->description,
            'tester_name' => $this->tester_name,
            'status' => TestRun::normalizeStatus($this->status),
            'test_plan' => [
                'id' => $this->test_plan_id,
                'name' => $this->testPlan?->name,
                'project' => [
                    'id' => $this->testPlan?->project_id,
                    'name' => $this->testPlan?->project?->project_name,
                ],
            ],
            'assigned_test_cases_count' => $this->whenCounted('testCases'),
            'execution_summary' => $this->executionSummary(),
            'test_cases' => $this->whenLoaded(
                'testCases',
                fn (): array => $this->testCases
                    ->map(fn ($testCase): array => [
                        'id' => $testCase->id,
                        'case_key' => $testCase->case_key,
                        'title' => $testCase->title,
                        'priority' => $testCase->priority,
                        'automation_status' => $testCase->automation_status,
                        'test_behavior' => $testCase->test_behavior,
                        'test_type' => $testCase->test_type,
                        'execution_status' => TestRun::normalizeCaseStatus(
                            (string) ($testCase->pivot->execution_status ?? TestRun::CASE_STATUS_NOT_RUN),
                        ),
                        'module' => [
                            'id' => $testCase->module_id,
                            'name' => $testCase->module?->name,
                        ],
                    ])
                    ->all(),
            ),
            'execution_started_at' => $this->execution_started_at?->toIso8601String(),
            'execution_completed_at' => $this->execution_completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{not_run: int, passed: int, failed: int, in_testing: int, skipped: int, blocked: int, completed: int, total: int, progress: int}
     */
    private function executionSummary(): array
    {
        /** @var Collection<int, mixed> $testCases */
        $testCases = $this->relationLoaded('testCases')
            ? $this->testCases
            : collect();

        $statuses = $testCases
            ->map(fn ($testCase): string => TestRun::normalizeCaseStatus(
                (string) ($testCase->pivot->execution_status ?? TestRun::CASE_STATUS_NOT_RUN),
            ))
            ->values();

        $total = $statuses->count();
        $passed = $statuses->filter(fn (string $status): bool => $status === TestRun::CASE_STATUS_PASSED)->count();
        $failed = $statuses->filter(fn (string $status): bool => $status === TestRun::CASE_STATUS_FAILED)->count();
        $inTesting = $statuses->filter(fn (string $status): bool => $status === TestRun::CASE_STATUS_IN_TESTING)->count();
        $skipped = $statuses->filter(fn (string $status): bool => $status === TestRun::CASE_STATUS_SKIPPED)->count();
        $blocked = $statuses->filter(fn (string $status): bool => $status === TestRun::CASE_STATUS_BLOCKED)->count();
        $notRun = $statuses->filter(fn (string $status): bool => $status === TestRun::CASE_STATUS_NOT_RUN)->count();
        $completed = $passed;

        return [
            'not_run' => $notRun,
            'passed' => $passed,
            'failed' => $failed,
            'in_testing' => $inTesting,
            'skipped' => $skipped,
            'blocked' => $blocked,
            'completed' => $completed,
            'total' => $total,
            'progress' => $total === 0 ? 0 : (int) round(($passed / $total) * 100),
        ];
    }
}
