<?php

namespace App\Services;

use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TestRunService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TestRun>
     */
    public function paginateForUser(User $user, array $filters = [], ?int $activeProjectId = null): LengthAwarePaginator
    {
        $perPage = max(5, min(50, (int) ($filters['per_page'] ?? 10)));

        return $this->applyFilters($this->queryForUser($user, $activeProjectId), $filters)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, TestRun>
     */
    public function paginateForTestPlan(TestPlan $testPlan, int $perPage = 10): LengthAwarePaginator
    {
        return TestRun::query()
            ->with([
                'testPlan.project:project_id,project_name,user_id',
                'testCases:id,project_id,module_id,case_key,title,priority,automation_status,test_behavior,test_type',
                'testCases.module:id,name',
            ])
            ->withCount('testCases')
            ->where('test_plan_id', $testPlan->id)
            ->latest()
            ->paginate(max(5, min(50, $perPage)));
    }

    /**
     * @return array<string, int>
     */
    public function getStats(User $user, ?int $activeProjectId = null): array
    {
        $query = $this->queryForUser($user, $activeProjectId)->toBase();

        return [
            'totalRuns' => (clone $query)->count(),
            'newRuns' => (clone $query)->where('status', TestRun::STATUS_NEW)->count(),
            'activeRuns' => (clone $query)
                ->where('status', TestRun::STATUS_IN_PROGRESS)
                ->count(),
            'doneRuns' => (clone $query)->where('status', TestRun::STATUS_COMPLETED)->count(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, project: array{id: int, name: string|null}, test_cases: array<int, array{id: int, case_key: string, title: string, priority: string, automation_status: string, test_behavior: string, test_type: string, module: array{id: int, name: string|null}}>}>
     */
    public function getTestPlanOptions(User $user, ?int $activeProjectId = null): array
    {
        $testPlansQuery = TestPlan::query()
            ->with([
                'project:project_id,project_name,user_id',
                'testCases:id,project_id,module_id,case_key,title,priority,automation_status,test_behavior,test_type',
                'testCases.module:id,name',
            ])
            ->whereIn('project_id', $user->projects()->select('project_id'))
            ->orderBy('name');

        if ($activeProjectId !== null) {
            $testPlansQuery->where('project_id', $activeProjectId);
        }

        $testPlans = $testPlansQuery->get();

        $options = [];

        foreach ($testPlans as $testPlan) {
            $testCases = [];

            foreach ($testPlan->testCases as $testCase) {
                $testCases[] = [
                    'id' => $testCase->id,
                    'case_key' => $testCase->case_key,
                    'title' => $testCase->title,
                    'priority' => $testCase->priority,
                    'automation_status' => $testCase->automation_status,
                    'test_behavior' => $testCase->test_behavior,
                    'test_type' => $testCase->test_type,
                    'module' => [
                        'id' => $testCase->module_id,
                        'name' => $testCase->module?->name,
                    ],
                ];
            }

            $options[] = [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
                'project' => [
                    'id' => $testPlan->project_id,
                    'name' => $testPlan->project?->project_name,
                ],
                'test_cases' => $testCases,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $testCaseIds
     */
    public function store(array $data, array $testCaseIds): TestRun
    {
        return DB::transaction(function () use ($data, $testCaseIds): TestRun {
            $testRun = TestRun::query()->create([
                ...$data,
                'status' => TestRun::STATUS_NEW,
            ]);
            $this->syncTestCases($testRun, $testCaseIds);

            return $this->getDetail($testRun->fresh());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $testCaseIds
     */
    public function update(TestRun $testRun, array $data, array $testCaseIds): TestRun
    {
        return DB::transaction(function () use ($testRun, $data, $testCaseIds): TestRun {
            unset($data['status']);

            $testRun->update($data);
            $this->syncTestCases($testRun, $testCaseIds);
            $this->syncLifecycleStatus($testRun);

            return $this->getDetail($testRun->fresh());
        });
    }

    public function start(TestRun $testRun): TestRun
    {
        if (TestRun::normalizeStatus($testRun->status) === TestRun::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Completed test runs cannot be started.',
            ]);
        }

        DB::transaction(function () use ($testRun): void {
            if (TestRun::normalizeStatus($testRun->status) === TestRun::STATUS_NEW) {
                $testRun->forceFill([
                    'status' => TestRun::STATUS_IN_PROGRESS,
                    'execution_started_at' => $testRun->execution_started_at ?? now(),
                ])->save();
            } elseif ($testRun->execution_started_at === null) {
                $testRun->forceFill([
                    'execution_started_at' => now(),
                ])->save();
            }
        });

        return $this->getDetail($testRun->fresh());
    }

    public function updateCaseStatus(TestRun $testRun, TestCase $testCase, string $status): TestRun
    {
        if (TestRun::normalizeStatus($testRun->status) === TestRun::STATUS_NEW) {
            throw ValidationException::withMessages([
                'status' => 'Start the test run before updating execution statuses.',
            ]);
        }

        $isAttached = $testRun->testCases()
            ->whereKey($testCase->id)
            ->exists();

        if (! $isAttached) {
            throw ValidationException::withMessages([
                'test_case_id' => 'The selected test case is not assigned to this test run.',
            ]);
        }

        $testRun->testCases()->updateExistingPivot($testCase->id, [
            'execution_status' => $status,
        ]);
        $testRun->increment('api_version');
        $this->syncLifecycleStatus($testRun);

        return $this->getDetail($testRun->fresh());
    }

    public function getDetail(TestRun $testRun): TestRun
    {
        return $testRun->load([
            'testPlan.project:project_id,project_name,user_id',
            'testCases:id,project_id,module_id,case_key,title,priority,automation_status,test_behavior,test_type',
            'testCases.module:id,name',
        ])->loadCount('testCases');
    }

    public function refreshLifecycleStatus(TestRun $testRun): void
    {
        $this->syncLifecycleStatus($testRun);
    }

    /**
     * @param  array<int, int>  $testCaseIds
     * @return array<int, int>
     */
    private function normalizeTestCaseIds(array $testCaseIds): array
    {
        return array_values(array_unique(array_map('intval', $testCaseIds)));
    }

    /**
     * @param  array<int, int>  $testCaseIds
     */
    private function syncTestCases(TestRun $testRun, array $testCaseIds): void
    {
        $normalizedIds = $this->normalizeTestCaseIds($testCaseIds);
        $existingStatuses = $testRun->testCases()
            ->pluck('test_case_test_run.execution_status', 'test_cases.id')
            ->mapWithKeys(fn (mixed $status, mixed $testCaseId): array => [
                (int) $testCaseId => TestRun::normalizeCaseStatus((string) $status),
            ])
            ->all();

        $syncPayload = [];

        foreach ($normalizedIds as $testCaseId) {
            $syncPayload[$testCaseId] = [
                'execution_status' => $existingStatuses[$testCaseId] ?? TestRun::CASE_STATUS_NOT_RUN,
            ];
        }

        $testRun->testCases()->sync($syncPayload);
    }

    private function syncLifecycleStatus(TestRun $testRun): void
    {
        $testRun->load('testCases');

        $statuses = $testRun->testCases
            ->map(fn (TestCase $testCase): string => TestRun::normalizeCaseStatus(
                (string) ($testCase->pivot->execution_status ?? TestRun::CASE_STATUS_NOT_RUN),
            ));

        $hasSelectedCases = $statuses->isNotEmpty();
        $allCasesPassed = $hasSelectedCases
            && $statuses->every(fn (string $status): bool => $status === TestRun::CASE_STATUS_PASSED);
        $normalizedStatus = TestRun::normalizeStatus($testRun->status);

        if ($allCasesPassed && $normalizedStatus !== TestRun::STATUS_COMPLETED) {
            $testRun->forceFill([
                'status' => TestRun::STATUS_COMPLETED,
                'execution_completed_at' => now(),
            ])->save();

            return;
        }

        if (! $allCasesPassed && $normalizedStatus === TestRun::STATUS_COMPLETED) {
            $testRun->forceFill([
                'status' => TestRun::STATUS_IN_PROGRESS,
                'execution_completed_at' => null,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<TestRun>  $query
     * @return Builder<TestRun>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $environment = (string) ($filters['environment'] ?? 'all');
        $priority = (string) ($filters['priority'] ?? 'all');
        $testPlanId = isset($filters['test_plan_id']) ? (int) $filters['test_plan_id'] : null;

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('tester_name', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhereHas('testPlan', function (Builder $planQuery) use ($search): void {
                        $planQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('testCases', function (Builder $caseQuery) use ($search): void {
                        $caseQuery
                            ->where('case_key', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($status, TestRun::statuses(), true)) {
            $query->where('status', $status);
        }

        if (in_array($environment, TestRun::environments(), true)) {
            $query->where('environment', $environment);
        }

        if (in_array($priority, TestRun::priorities(), true)) {
            $query->where('priority', $priority);
        }

        if ($testPlanId !== null && $testPlanId > 0) {
            $query->where('test_plan_id', $testPlanId);
        }

        return $query;
    }

    /**
     * @return Builder<TestRun>
     */
    private function queryForUser(User $user, ?int $activeProjectId = null): Builder
    {
        $query = TestRun::query()
            ->with([
                'testPlan.project:project_id,project_name,user_id',
                'testCases:id,project_id,module_id,case_key,title,priority,automation_status,test_behavior,test_type',
                'testCases.module:id,name',
            ])
            ->withCount('testCases')
            ->whereHas('testPlan.project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('user_id', $user->id);
            });

        if ($activeProjectId !== null) {
            $query->whereHas('testPlan', function (Builder $planQuery) use ($activeProjectId): void {
                $planQuery->where('project_id', $activeProjectId);
            });
        }

        return $query;
    }
}
