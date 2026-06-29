<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRepositoryModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TestRepositoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TestCase>
     */
    public function paginateCasesForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(5, min(50, (int) ($filters['per_page'] ?? 10)));

        return $this->applyCaseFilters($user, $this->caseQueryForUser($user), $filters)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function getStats(User $user): array
    {
        $query = $this->caseQueryForUser($user)->toBase();

        return [
            'totalCases' => (clone $query)->count(),
            'automatedCases' => (clone $query)
                ->where('automation_status', TestCase::AUTOMATION_AUTOMATED)
                ->count(),
            'manualCases' => (clone $query)
                ->where('automation_status', TestCase::AUTOMATION_MANUAL)
                ->count(),
            'positiveCases' => (clone $query)
                ->where('test_behavior', TestCase::BEHAVIOR_POSITIVE)
                ->count(),
            'negativeCases' => (clone $query)
                ->where('test_behavior', TestCase::BEHAVIOR_NEGATIVE)
                ->count(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getProjectOptions(User $user): array
    {
        return $user->projects()
            ->orderBy('project_name')
            ->get(['project_id', 'project_name'])
            ->map(fn (Project $project): array => [
                'id' => $project->project_id,
                'name' => $project->project_name,
            ])
            ->all();
    }

    /**
     * @return array<int, TestRepositoryModule>
     */
    public function getModulesForUser(User $user): array
    {
        return TestRepositoryModule::query()
            ->withCount(['children', 'testCases'])
            ->whereIn('project_id', $user->projects()->select('project_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return array<int, TestRepositoryModule>
     */
    public function getModulesForProject(Project $project): array
    {
        return TestRepositoryModule::query()
            ->withCount(['children', 'testCases'])
            ->where('project_id', $project->project_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getTestPlanOptionsForProject(Project $project): array
    {
        return $project->testPlans()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($testPlan): array => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeModule(array $data): TestRepositoryModule
    {
        $data['sort_order'] = $this->nextModuleSortOrder(
            (int) $data['project_id'],
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
        );

        return TestRepositoryModule::query()
            ->create($data)
            ->load(['project'])
            ->loadCount(['children', 'testCases']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateModule(TestRepositoryModule $module, array $data): TestRepositoryModule
    {
        $parentChanged = array_key_exists('parent_id', $data)
            && (int) ($data['parent_id'] ?? 0) !== (int) ($module->parent_id ?? 0);

        if ($parentChanged) {
            $data['sort_order'] = $this->nextModuleSortOrder(
                (int) $data['project_id'],
                isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                $module->id,
            );
        }

        $module->update($data);

        return $module->load(['project'])->loadCount(['children', 'testCases']);
    }

    public function deleteModule(TestRepositoryModule $module): void
    {
        if ($module->children()->exists() || $module->testCases()->exists()) {
            throw ValidationException::withMessages([
                'module' => 'Delete child folders and test cases before deleting this folder.',
            ]);
        }

        $module->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeCase(array $data): TestCase
    {
        return DB::transaction(function () use ($data): TestCase {
            return $this->storeCaseWithinTransaction($data);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     * @return Collection<int, TestCase>
     */
    public function bulkStoreCases(Project $project, int $moduleId, array $cases): Collection
    {
        return DB::transaction(function () use ($project, $moduleId, $cases): Collection {
            $createdCases = new Collection;

            foreach ($cases as $case) {
                $createdCases->push($this->storeCaseWithinTransaction([
                    ...$case,
                    'project_id' => $project->project_id,
                    'module_id' => $moduleId,
                ]));
            }

            return $createdCases;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCase(TestCase $testCase, array $data): TestCase
    {
        return DB::transaction(function () use ($testCase, $data): TestCase {
            $replaceSteps = array_key_exists('steps', $data);
            /** @var array<int, array{action: string, expected_result: string}>|null $steps */
            $steps = $replaceSteps ? $data['steps'] : null;
            unset($data['steps']);

            $testCase->update($data);

            if ($steps !== null) {
                $this->syncSteps($testCase, $steps);
                $testCase->increment('api_version');
            }

            return $testCase->load(['project', 'module', 'steps']);
        });
    }

    /**
     * @param  Builder<TestCase>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TestCase>
     */
    private function applyCaseFilters(User $user, Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $moduleId = (int) ($filters['module_id'] ?? 0);
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

        if ($moduleId > 0) {
            $moduleIds = $this->moduleAndDescendantIds($user, $moduleId);

            if ($moduleIds !== []) {
                $query->whereIn('module_id', $moduleIds);
            }
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
    private function caseQueryForUser(User $user): Builder
    {
        return TestCase::query()
            ->with([
                'module:id,project_id,parent_id,name,key_prefix',
                'project:project_id,project_name,user_id',
            ])
            ->whereIn('project_id', $user->projects()->select('project_id'));
    }

    /**
     * @return array<int, int>
     */
    private function moduleAndDescendantIds(User $user, int $moduleId): array
    {
        $modules = TestRepositoryModule::query()
            ->whereIn('project_id', $user->projects()->select('project_id'))
            ->get(['id', 'parent_id']);

        if (! $modules->contains('id', $moduleId)) {
            return [];
        }

        $ids = [$moduleId];
        $frontier = [$moduleId];

        while ($frontier !== []) {
            $children = $modules
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->values()
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique([...$ids, ...$frontier]));
        }

        return $ids;
    }

    private function nextCaseKey(TestRepositoryModule $module): string
    {
        $prefix = 'TC-'.strtoupper($module->key_prefix).'-';
        $latestKey = TestCase::query()
            ->where('project_id', $module->project_id)
            ->where('case_key', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('case_key');

        $nextNumber = 1;

        if (is_string($latestKey) && preg_match('/-(\d+)$/', $latestKey, $matches) === 1) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
    }

    private function nextModuleSortOrder(int $projectId, ?int $parentId, ?int $ignoreModuleId = null): int
    {
        $query = TestRepositoryModule::query()
            ->where('project_id', $projectId)
            ->where('parent_id', $parentId);

        if ($ignoreModuleId !== null) {
            $query->whereKeyNot($ignoreModuleId);
        }

        $currentMax = (int) $query->max('sort_order');

        return $currentMax + 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeCaseWithinTransaction(array $data): TestCase
    {
        /** @var TestRepositoryModule $module */
        $module = TestRepositoryModule::query()
            ->whereKey($data['module_id'])
            ->lockForUpdate()
            ->firstOrFail();

        /** @var array<int, array{action: string, expected_result: string}> $steps */
        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        $testCase = TestCase::query()->create([
            ...$data,
            'case_key' => $this->nextCaseKey($module),
        ]);

        $this->syncSteps($testCase, $steps);

        return $testCase->load(['project', 'module', 'steps']);
    }

    /**
     * @param  array<int, array{action: string, expected_result: string}>  $steps
     */
    private function syncSteps(TestCase $testCase, array $steps): void
    {
        $testCase->steps()->delete();

        foreach (array_values($steps) as $index => $step) {
            $testCase->steps()->create([
                'position' => $index + 1,
                'action' => $step['action'],
                'expected_result' => $step['expected_result'],
            ]);
        }
    }
}
