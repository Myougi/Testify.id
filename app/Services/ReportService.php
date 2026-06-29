<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @param  array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>}  $filters
     * @return array<string, mixed>
     */
    public function getPayload(Project $project, array $filters): array
    {
        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate = Carbon::parse($filters['end_date'])->endOfDay();
        $planId = $filters['plan_id'];
        $totalTestCases = $this->testCaseQuery($project, $planId)->count();
        $executedTestCases = $this->executionPivotQuery($project, $planId, $startDate, $endDate)
            ->where('test_case_test_run.execution_status', '!=', TestRun::CASE_STATUS_NOT_RUN)
            ->distinct('test_case_test_run.test_case_id')
            ->count('test_case_test_run.test_case_id');
        $coverage = $totalTestCases === 0
            ? 0.0
            : round(($executedTestCases / $totalTestCases) * 100, 1);

        $testRunQuery = $this->testRunQuery($project, $planId, $startDate, $endDate);
        $issueQuery = $this->issueQuery($project, $planId, $startDate, $endDate);

        return [
            'metrics' => [
                'totalTestCases' => $totalTestCases,
                'totalTestRuns' => (clone $testRunQuery)->count(),
                'totalIssues' => (clone $issueQuery)->count(),
                'executionCoverage' => $coverage,
            ],
            'trend' => $this->issueTrend($project, $planId, $startDate, $endDate, $filters['grouping']),
            'distribution' => $this->issueDistribution($project, $planId, $startDate, $endDate),
            'results' => $this->testResultSummary($project, $planId, $startDate, $endDate),
            'workload' => $this->teamWorkload($project, $planId, $startDate, $endDate),
            'recentExecutions' => $this->recentExecutions($project, $planId, $startDate, $endDate),
            'filters' => $filters,
            'options' => [
                'hasActiveProject' => true,
                'project' => [
                    'id' => $project->project_id,
                    'name' => $project->project_name,
                ],
                'testPlans' => $this->testPlanOptions($project),
                'ranges' => $this->ranges(),
                'groupings' => $this->groupings(),
                'sections' => $this->sections(),
            ],
        ];
    }

    /**
     * @param  array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>}  $filters
     * @return array<string, mixed>
     */
    public function emptyPayload(array $filters): array
    {
        return [
            'metrics' => [
                'totalTestCases' => 0,
                'totalTestRuns' => 0,
                'totalIssues' => 0,
                'executionCoverage' => 0.0,
            ],
            'trend' => [],
            'distribution' => collect(Issue::severities())
                ->map(fn (string $severity): array => [
                    'label' => $severity,
                    'value' => 0,
                ])
                ->all(),
            'results' => [
                'total' => 0,
                'statuses' => $this->emptyResultStatuses(),
            ],
            'workload' => [],
            'recentExecutions' => [],
            'filters' => $filters,
            'options' => [
                'hasActiveProject' => false,
                'project' => null,
                'testPlans' => [],
                'ranges' => $this->ranges(),
                'groupings' => $this->groupings(),
                'sections' => $this->sections(),
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function sections(): array
    {
        return [
            ['value' => 'metrics', 'label' => 'Metric Cards'],
            ['value' => 'issue_trend', 'label' => 'Issue Trend'],
            ['value' => 'issue_distribution', 'label' => 'Issue Distribution'],
            ['value' => 'result_summary', 'label' => 'Test Results Summary'],
            ['value' => 'team_workload', 'label' => 'Team Workload'],
            ['value' => 'recent_executions', 'label' => 'Recent Executions'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function ranges(): array
    {
        return [
            ['value' => '7d', 'label' => 'Last 7 Days'],
            ['value' => '30d', 'label' => 'Last 30 Days'],
            ['value' => 'custom', 'label' => 'Custom Range'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function groupings(): array
    {
        return [
            ['value' => 'daily', 'label' => 'Daily'],
            ['value' => 'weekly', 'label' => 'Weekly'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function testPlanOptions(Project $project): array
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
     * @return array<int, array{label: string, bucket: string, value: int}>
     */
    private function issueTrend(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate, string $grouping): array
    {
        $dailyCounts = $this->issueQuery($project, $planId, $startDate, $endDate)
            ->selectRaw('DATE(created_at) as bucket, COUNT(*) as aggregate')
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket')
            ->mapWithKeys(fn (mixed $count, mixed $bucket): array => [(string) $bucket => (int) $count])
            ->all();

        if ($grouping === 'weekly') {
            return $this->weeklyTrend($dailyCounts, $startDate, $endDate);
        }

        $trend = [];
        $cursor = $startDate->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $bucket = $cursor->toDateString();
            $trend[] = [
                'label' => $cursor->format('D'),
                'bucket' => $bucket,
                'value' => $dailyCounts[$bucket] ?? 0,
            ];
            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @param  array<string, int>  $dailyCounts
     * @return array<int, array{label: string, bucket: string, value: int}>
     */
    private function weeklyTrend(array $dailyCounts, Carbon $startDate, Carbon $endDate): array
    {
        $buckets = [];
        $cursor = $startDate->copy()->startOfWeek()->startOfDay();

        while ($cursor->lte($endDate)) {
            $bucket = $cursor->toDateString();
            $buckets[$bucket] = [
                'label' => $cursor->format('M j'),
                'bucket' => $bucket,
                'value' => 0,
            ];
            $cursor->addWeek();
        }

        foreach ($dailyCounts as $date => $count) {
            $bucket = Carbon::parse($date)->startOfWeek()->toDateString();

            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['value'] += $count;
            }
        }

        return array_values($buckets);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function issueDistribution(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): array
    {
        $counts = $this->issueQuery($project, $planId, $startDate, $endDate)
            ->selectRaw('severity, COUNT(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity')
            ->mapWithKeys(fn (mixed $count, mixed $severity): array => [(string) $severity => (int) $count])
            ->all();

        return collect(Issue::severities())
            ->map(fn (string $severity): array => [
                'label' => $severity,
                'value' => $counts[$severity] ?? 0,
            ])
            ->all();
    }

    /**
     * @return array{total: int, statuses: array<int, array{label: string, key: string, value: int, percent: int}>}
     */
    private function testResultSummary(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): array
    {
        $counts = $this->executionPivotQuery($project, $planId, $startDate, $endDate)
            ->selectRaw('test_case_test_run.execution_status, COUNT(*) as aggregate')
            ->groupBy('test_case_test_run.execution_status')
            ->pluck('aggregate', 'test_case_test_run.execution_status')
            ->mapWithKeys(fn (mixed $count, mixed $status): array => [
                TestRun::normalizeCaseStatus((string) $status) => (int) $count,
            ])
            ->all();

        $statuses = array_map(
            function (array $status) use ($counts): array {
                $status['value'] = $counts[$status['label']] ?? 0;

                return $status;
            },
            $this->emptyResultStatuses(),
        );
        $total = array_sum(array_map(fn (array $status): int => $status['value'], $statuses));

        return [
            'total' => $total,
            'statuses' => array_values(
                array_map(
                    function (array $status) use ($total): array {
                        $status['percent'] = $total === 0 ? 0 : (int) round(($status['value'] / $total) * 100);

                        return $status;
                    },
                    $statuses,
                ),
            ),
        ];
    }

    /**
     * @return array<int, array{name: string, initials: string, activeRuns: int, assignedCases: int}>
     */
    private function teamWorkload(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): array
    {
        $query = DB::table('test_runs')
            ->join('test_plans', 'test_plans.id', '=', 'test_runs.test_plan_id')
            ->leftJoin('test_case_test_run', 'test_case_test_run.test_run_id', '=', 'test_runs.id')
            ->where('test_plans.project_id', $project->project_id)
            ->where('test_runs.status', TestRun::STATUS_IN_PROGRESS)
            ->whereBetween('test_runs.created_at', [$startDate, $endDate]);

        if ($planId !== null) {
            $query->where('test_runs.test_plan_id', $planId);
        }

        return $query
            ->selectRaw('test_runs.tester_name, COUNT(DISTINCT test_runs.id) as active_runs, COUNT(test_case_test_run.test_case_id) as assigned_cases')
            ->groupBy('test_runs.tester_name')
            ->orderByDesc('assigned_cases')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->tester_name,
                'initials' => $this->initials((string) $row->tester_name),
                'activeRuns' => (int) $row->active_runs,
                'assignedCases' => (int) $row->assigned_cases,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, date: string|null, plan: array{id: int, name: string|null}, result: string, tester: string, status: string, href: string}>
     */
    private function recentExecutions(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): array
    {
        return $this->testRunQuery($project, $planId, $startDate, $endDate)
            ->with([
                'testPlan:id,project_id,name',
                'testCases:id',
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (TestRun $testRun): array => [
                'id' => $testRun->id,
                'name' => $testRun->name,
                'date' => $testRun->created_at?->toDateString(),
                'plan' => [
                    'id' => $testRun->test_plan_id,
                    'name' => $testRun->testPlan?->name,
                ],
                'result' => $this->executionResult($testRun),
                'tester' => $testRun->tester_name,
                'status' => TestRun::normalizeStatus($testRun->status),
                'href' => route('test-executions.show', $testRun),
            ])
            ->all();
    }

    private function executionResult(TestRun $testRun): string
    {
        $statuses = $testRun->testCases
            ->map(fn (TestCase $testCase): string => TestRun::normalizeCaseStatus(
                (string) ($testCase->pivot->execution_status ?? TestRun::CASE_STATUS_NOT_RUN),
            ));

        if ($statuses->contains(TestRun::CASE_STATUS_FAILED)) {
            return 'Fail';
        }

        if ($statuses->contains(TestRun::CASE_STATUS_BLOCKED)) {
            return 'Blocked';
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn (string $status): bool => $status === TestRun::CASE_STATUS_PASSED)) {
            return 'Pass';
        }

        return TestRun::normalizeStatus($testRun->status);
    }

    /**
     * @return array<int, array{label: string, key: string, value: int, percent: int}>
     */
    private function emptyResultStatuses(): array
    {
        return [
            ['label' => TestRun::CASE_STATUS_PASSED, 'key' => 'passed', 'value' => 0, 'percent' => 0],
            ['label' => TestRun::CASE_STATUS_FAILED, 'key' => 'failed', 'value' => 0, 'percent' => 0],
            ['label' => TestRun::CASE_STATUS_IN_TESTING, 'key' => 'in_testing', 'value' => 0, 'percent' => 0],
            ['label' => TestRun::CASE_STATUS_BLOCKED, 'key' => 'blocked', 'value' => 0, 'percent' => 0],
            ['label' => TestRun::CASE_STATUS_SKIPPED, 'key' => 'skipped', 'value' => 0, 'percent' => 0],
            ['label' => TestRun::CASE_STATUS_NOT_RUN, 'key' => 'not_run', 'value' => 0, 'percent' => 0],
        ];
    }

    /**
     * @return EloquentBuilder<TestCase>
     */
    private function testCaseQuery(Project $project, ?int $planId): EloquentBuilder
    {
        $query = TestCase::query()
            ->where('project_id', $project->project_id);

        if ($planId !== null) {
            $query->whereHas('testPlans', fn ($planQuery) => $planQuery->whereKey($planId));
        }

        return $query;
    }

    /**
     * @return EloquentBuilder<TestRun>
     */
    private function testRunQuery(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): EloquentBuilder
    {
        $query = TestRun::query()
            ->whereHas('testPlan', fn ($planQuery) => $planQuery->where('project_id', $project->project_id))
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($planId !== null) {
            $query->where('test_plan_id', $planId);
        }

        return $query;
    }

    /**
     * @return EloquentBuilder<Issue>
     */
    private function issueQuery(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): EloquentBuilder
    {
        $query = Issue::query()
            ->where('project_id', $project->project_id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($planId !== null) {
            $query->where('test_plan_id', $planId);
        }

        return $query;
    }

    private function executionPivotQuery(Project $project, ?int $planId, Carbon $startDate, Carbon $endDate): Builder
    {
        $query = DB::table('test_case_test_run')
            ->join('test_runs', 'test_runs.id', '=', 'test_case_test_run.test_run_id')
            ->join('test_plans', 'test_plans.id', '=', 'test_runs.test_plan_id')
            ->where('test_plans.project_id', $project->project_id)
            ->whereBetween('test_runs.created_at', [$startDate, $endDate]);

        if ($planId !== null) {
            $query->where('test_runs.test_plan_id', $planId);
        }

        return $query;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'QA';
    }
}
