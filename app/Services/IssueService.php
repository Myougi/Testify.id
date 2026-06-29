<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class IssueService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Issue>
     */
    public function paginateForProject(Project $project, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(5, min(50, (int) ($filters['per_page'] ?? 10)));

        return $this->applyFilters($this->queryForProject($project), $filters)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{totalActiveIssues: int, criticalIssues: int, resolutionVelocityDays: float|null}
     */
    public function getStats(Project $project): array
    {
        $query = Issue::query()->where('project_id', $project->project_id);

        $closedIssues = (clone $query)
            ->where('status', Issue::STATUS_SOLVED)
            ->whereNotNull('closed_at')
            ->get(['created_at', 'closed_at']);

        $velocity = $closedIssues->isEmpty()
            ? null
            : round($closedIssues
                ->avg(fn (Issue $issue): float => max(
                    0,
                    $issue->created_at?->diffInSeconds($issue->closed_at ?? $issue->created_at) ?? 0,
                ) / 86400), 1);

        return [
            'totalActiveIssues' => (clone $query)
                ->where('status', '!=', Issue::STATUS_SOLVED)
                ->count(),
            'criticalIssues' => (clone $query)
                ->where('status', '!=', Issue::STATUS_SOLVED)
                ->where('severity', Issue::SEVERITY_CRITICAL)
                ->count(),
            'resolutionVelocityDays' => $velocity,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getTestPlanOptions(Project $project): array
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
     * @return array<int, array{id: int, name: string, status: string, test_plan: array{id: int, name: string|null}}>
     */
    public function getTestRunOptions(Project $project): array
    {
        return TestRun::query()
            ->with('testPlan:id,project_id,name')
            ->whereHas('testPlan', function (Builder $query) use ($project): void {
                $query->where('project_id', $project->project_id);
            })
            ->orderByDesc('created_at')
            ->get(['id', 'test_plan_id', 'name', 'status'])
            ->map(fn (TestRun $testRun): array => [
                'id' => $testRun->id,
                'name' => $testRun->name,
                'status' => $testRun->status,
                'test_plan' => [
                    'id' => $testRun->test_plan_id,
                    'name' => $testRun->testPlan?->name,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Project $project, array $data): Issue
    {
        $issue = Issue::query()->create([
            ...$this->normalizeData($data),
            'project_id' => $project->project_id,
        ]);

        return $this->getDetail($issue);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Issue $issue, array $data): Issue
    {
        $issue->update($this->normalizeData($data, $issue));

        return $this->getDetail($issue->fresh());
    }

    public function getDetail(Issue $issue): Issue
    {
        return $issue->load([
            'project:project_id,project_name,user_id',
            'testPlan:id,project_id,name',
            'testRun:id,test_plan_id,name,status',
            'testRun.testPlan:id,project_id,name',
        ]);
    }

    /**
     * @param  Builder<Issue>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Issue>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $severity = (string) ($filters['severity'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'all');

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reporter', 'like', "%{$search}%")
                    ->orWhere('assignee', 'like', "%{$search}%")
                    ->orWhere('environment', 'like', "%{$search}%")
                    ->orWhere('defect_type', 'like', "%{$search}%")
                    ->orWhere('expected_result', 'like', "%{$search}%")
                    ->orWhere('actual_result', 'like', "%{$search}%")
                    ->orWhereHas('testPlan', function (Builder $planQuery) use ($search): void {
                        $planQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('testRun', function (Builder $runQuery) use ($search): void {
                        $runQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($severity, Issue::severities(), true)) {
            $query->where('severity', $severity);
        }

        if (in_array($status, Issue::statuses(), true)) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * @return Builder<Issue>
     */
    private function queryForProject(Project $project): Builder
    {
        return Issue::query()
            ->with([
                'project:project_id,project_name,user_id',
                'testPlan:id,project_id,name',
                'testRun:id,test_plan_id,name,status',
                'testRun.testPlan:id,project_id,name',
            ])
            ->where('project_id', $project->project_id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data, ?Issue $existing = null): array
    {
        $testRunId = isset($data['test_run_id']) ? (int) $data['test_run_id'] : null;
        $testPlanId = isset($data['test_plan_id']) ? (int) $data['test_plan_id'] : null;
        $testRun = $testRunId
            ? TestRun::query()->find($testRunId, ['id', 'test_plan_id'])
            : null;

        if ($testRun && ! $testPlanId) {
            $testPlanId = $testRun->test_plan_id;
        }

        $status = (string) $data['status'];
        $closedAt = $existing?->closed_at;

        if ($status === Issue::STATUS_SOLVED) {
            $closedAt ??= Carbon::now();
        } else {
            $closedAt = null;
        }

        return [
            'test_plan_id' => $testPlanId ?: null,
            'test_run_id' => $testRunId ?: null,
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'expected_result' => $this->nullableString($data['expected_result'] ?? null),
            'actual_result' => $this->nullableString($data['actual_result'] ?? null),
            'priority' => (string) $data['priority'],
            'severity' => (string) $data['severity'],
            'status' => $status,
            'reporter' => trim((string) $data['reporter']),
            'assignee' => $this->nullableString($data['assignee'] ?? null),
            'environment' => $this->nullableString($data['environment'] ?? null),
            'defect_type' => $this->nullableString($data['defect_type'] ?? null),
            'closed_at' => $closedAt,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
