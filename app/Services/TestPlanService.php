<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TestPlanService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TestPlan>
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
     * @return array<string, int>
     */
    public function getStats(User $user, ?int $activeProjectId = null): array
    {
        $query = $this->queryForUser($user, $activeProjectId)->toBase();
        $today = Carbon::today()->toDateString();

        return [
            'totalPlans' => (clone $query)->count(),
            'activePlans' => (clone $query)
                ->where('status', TestPlan::STATUS_IN_PROGRESS)
                ->count(),
            'completedPlans' => (clone $query)
                ->where('status', TestPlan::STATUS_COMPLETED)
                ->count(),
            'overduePlans' => (clone $query)
                ->whereNotIn('status', [
                    TestPlan::STATUS_COMPLETED,
                    TestPlan::STATUS_ARCHIVED,
                ])
                ->whereDate('due_date', '<', $today)
                ->count(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getProjectOptions(User $user): array
    {
        return $user->accessibleProjects()
            ->orderBy('project_name')
            ->get(['project_id', 'project_name'])
            ->map(fn (Project $project): array => [
                'id' => $project->project_id,
                'name' => $project->project_name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): TestPlan
    {
        $testPlan = TestPlan::query()->create($data);

        return $testPlan->load(['project'])->loadCount('testCases');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TestPlan $testPlan, array $data): TestPlan
    {
        $testPlan->update($data);

        return $testPlan->load(['project'])->loadCount('testCases');
    }

    public function getDetail(TestPlan $testPlan): TestPlan
    {
        return $testPlan->load([
            'project:project_id,project_name,user_id',
            'testCases:id,project_id,module_id,case_key,title,priority,automation_status,test_behavior,test_type',
            'testCases.module:id,name',
        ])->loadCount('testCases');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TestCase>
     */
    public function paginateAvailableCases(TestPlan $testPlan, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(5, min(50, (int) ($filters['per_page'] ?? 10)));

        return $this->applyAvailableCaseFilters(
            $this->availableCaseQuery($testPlan),
            $filters,
        )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<int, int>  $testCaseIds
     */
    public function attachTestCases(TestPlan $testPlan, array $testCaseIds): TestPlan
    {
        $normalizedIds = array_values(array_unique($testCaseIds));

        $validCaseCount = TestCase::query()
            ->where('project_id', $testPlan->project_id)
            ->whereIn('id', $normalizedIds)
            ->count();

        if ($validCaseCount !== count($normalizedIds)) {
            throw ValidationException::withMessages([
                'test_case_ids' => 'One or more selected test cases do not belong to this project.',
            ]);
        }

        $testPlan->testCases()->syncWithoutDetaching($normalizedIds);
        $testPlan->increment('api_version');

        return $this->getDetail($testPlan->fresh());
    }

    public function detachTestCase(TestPlan $testPlan, TestCase $testCase): TestPlan
    {
        if ($testCase->project_id !== $testPlan->project_id) {
            throw ValidationException::withMessages([
                'test_case_id' => 'The selected test case does not belong to this test plan project.',
            ]);
        }

        $testPlan->testCases()->detach($testCase->id);
        $testPlan->increment('api_version');

        return $this->getDetail($testPlan->fresh());
    }

    /**
     * @param  Builder<TestPlan>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TestPlan>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $priority = (string) ($filters['priority'] ?? 'all');
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhere('scope', 'like', "%{$search}%")
                    ->orWhere('strategy', 'like', "%{$search}%")
                    ->orWhere('criteria', 'like', "%{$search}%")
                    ->orWhere('tester', 'like', "%{$search}%")
                    ->orWhere('release_version', 'like', "%{$search}%")
                    ->orWhereHas('project', function (Builder $projectQuery) use ($search): void {
                        $projectQuery->where('project_name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($status, TestPlan::statuses(), true)) {
            $query->where('status', $status);
        }

        if (in_array($priority, TestPlan::priorities(), true)) {
            $query->where('priority', $priority);
        }

        return $query;
    }

    /**
     * @return Builder<TestPlan>
     */
    private function queryForUser(User $user, ?int $activeProjectId = null): Builder
    {
        $query = TestPlan::query()
            ->with([
                'project:project_id,project_name,user_id',
            ])
            ->withCount('testCases')
            ->whereIn('project_id', $user->accessibleProjects()->select('project_id'));

        if ($activeProjectId !== null) {
            $query->where('project_id', $activeProjectId);
        }

        return $query;
    }

    /**
     * @param  Builder<TestCase>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TestCase>
     */
    private function applyAvailableCaseFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $moduleId = isset($filters['module_id']) ? (int) $filters['module_id'] : null;
        $priority = (string) ($filters['priority'] ?? 'all');
        $automationStatus = (string) ($filters['automation_status'] ?? 'all');
        $testBehavior = (string) ($filters['test_behavior'] ?? 'all');
        $testType = (string) ($filters['test_type'] ?? 'all');

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('case_key', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('preconditions', 'like', "%{$search}%")
                    ->orWhereHas('module', function (Builder $moduleQuery) use ($search): void {
                        $moduleQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($moduleId !== null && $moduleId > 0) {
            $query->where('module_id', $moduleId);
        }

        if (in_array($priority, TestCase::priorities(), true)) {
            $query->where('priority', $priority);
        }

        if (in_array($automationStatus, TestCase::automationStatuses(), true)) {
            $query->where('automation_status', $automationStatus);
        }

        if (in_array($testBehavior, TestCase::behaviors(), true)) {
            $query->where('test_behavior', $testBehavior);
        }

        if (in_array($testType, TestCase::types(), true)) {
            $query->where('test_type', $testType);
        }

        return $query;
    }

    /**
     * @return Builder<TestCase>
     */
    private function availableCaseQuery(TestPlan $testPlan): Builder
    {
        return TestCase::query()
            ->with([
                'module:id,name',
                'project:project_id,project_name,user_id',
            ])
            ->where('project_id', $testPlan->project_id)
            ->whereDoesntHave('testPlans', function (Builder $query) use ($testPlan): void {
                $query->whereKey($testPlan->id);
            });
    }
}
