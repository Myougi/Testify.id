<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestPlan;
use App\Models\TestRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getPayload(Project $project): array
    {
        return [
            'metrics' => $this->metrics($project),
            'workload' => $this->workload($project),
            'recentIssues' => $this->recentIssues($project),
            'overduePlans' => $this->overduePlans($project),
            'options' => [
                'hasActiveProject' => true,
                'project' => [
                    'id' => $project->project_id,
                    'name' => $project->project_name,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(): array
    {
        return [
            'metrics' => [
                'totalTestCases' => 0,
                'notRun' => 0,
                'inTesting' => 0,
                'passed' => 0,
                'failed' => 0,
            ],
            'workload' => [],
            'recentIssues' => [],
            'overduePlans' => [],
            'options' => [
                'hasActiveProject' => false,
                'project' => null,
            ],
        ];
    }

    /**
     * @return array{totalTestCases: int, notRun: int, inTesting: int, passed: int, failed: int}
     */
    private function metrics(Project $project): array
    {
        $totalTestCases = $project->testCases()->count();
        $latestRunIds = $this->latestRunIdsForProject($project);
        $latestStatuses = DB::table('test_case_test_run')
            ->joinSub($latestRunIds, 'latest_runs', function ($join): void {
                $join
                    ->on('latest_runs.test_case_id', '=', 'test_case_test_run.test_case_id')
                    ->on('latest_runs.latest_run_id', '=', 'test_case_test_run.test_run_id');
            })
            ->selectRaw('test_case_test_run.execution_status, COUNT(*) as aggregate')
            ->groupBy('test_case_test_run.execution_status')
            ->get();

        $passed = 0;
        $failed = 0;
        $inTesting = 0;

        foreach ($latestStatuses as $latestStatus) {
            $caseStatus = TestRun::normalizeCaseStatus((string) $latestStatus->execution_status);
            $count = (int) $latestStatus->aggregate;

            if ($caseStatus === TestRun::CASE_STATUS_PASSED) {
                $passed += $count;

                continue;
            }

            if ($caseStatus === TestRun::CASE_STATUS_FAILED) {
                $failed += $count;

                continue;
            }

            if ($caseStatus === TestRun::CASE_STATUS_IN_TESTING) {
                $inTesting += $count;
            }
        }

        return [
            'totalTestCases' => $totalTestCases,
            'notRun' => max(0, $totalTestCases - $passed - $failed - $inTesting),
            'inTesting' => $inTesting,
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<int, array{name: string, initials: string, activeRuns: int, assignedCases: int}>
     */
    private function workload(Project $project): array
    {
        return DB::table('test_runs')
            ->join('test_plans', 'test_plans.id', '=', 'test_runs.test_plan_id')
            ->leftJoin('test_case_test_run', 'test_case_test_run.test_run_id', '=', 'test_runs.id')
            ->where('test_plans.project_id', $project->project_id)
            ->where('test_runs.status', TestRun::STATUS_IN_PROGRESS)
            ->selectRaw('test_runs.tester_name, COUNT(DISTINCT test_runs.id) as active_runs, COUNT(test_case_test_run.test_case_id) as assigned_cases')
            ->groupBy('test_runs.tester_name')
            ->orderByDesc('assigned_cases')
            ->orderBy('test_runs.tester_name')
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
     * @return array<int, array{id: int, issueKey: string, title: string, status: string, priority: string, severity: string, reporter: string, href: string}>
     */
    private function recentIssues(Project $project): array
    {
        return Issue::query()
            ->where('project_id', $project->project_id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Issue $issue): array => [
                'id' => $issue->id,
                'issueKey' => sprintf('ISS-%03d', $issue->id),
                'title' => $issue->title ?: $issue->description,
                'status' => $issue->status,
                'priority' => $issue->priority,
                'severity' => $issue->severity,
                'reporter' => $issue->reporter,
                'href' => route('issues.show', $issue),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, assignedTo: string, assignedInitials: string, dueDate: string, progress: int, href: string}>
     */
    private function overduePlans(Project $project): array
    {
        return TestPlan::query()
            ->withCount('testCases')
            ->where('project_id', $project->project_id)
            ->whereNotIn('status', [
                TestPlan::STATUS_COMPLETED,
                TestPlan::STATUS_ARCHIVED,
            ])
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (TestPlan $testPlan): array => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
                'assignedTo' => $testPlan->tester ?: 'Unassigned',
                'assignedInitials' => $this->initials($testPlan->tester ?: 'Unassigned'),
                'dueDate' => $testPlan->due_date?->toDateString() ?? '',
                'progress' => $this->planProgress($testPlan),
                'href' => route('test-plans.show', $testPlan),
            ])
            ->all();
    }

    private function planProgress(TestPlan $testPlan): int
    {
        $totalCases = $testPlan->test_cases_count ?? $testPlan->testCases()->count();

        if ($totalCases === 0) {
            return 0;
        }

        $latestRunIds = $this->latestRunIdsForPlan($testPlan);
        $passedCases = DB::table('test_case_test_run')
            ->joinSub($latestRunIds, 'latest_runs', function ($join): void {
                $join
                    ->on('latest_runs.test_case_id', '=', 'test_case_test_run.test_case_id')
                    ->on('latest_runs.latest_run_id', '=', 'test_case_test_run.test_run_id');
            })
            ->where('test_case_test_run.execution_status', TestRun::CASE_STATUS_PASSED)
            ->count();

        return (int) round(($passedCases / $totalCases) * 100);
    }

    private function latestRunIdsForProject(Project $project): \Illuminate\Database\Query\Builder
    {
        return DB::table('test_case_test_run')
            ->join('test_runs', 'test_runs.id', '=', 'test_case_test_run.test_run_id')
            ->join('test_plans', 'test_plans.id', '=', 'test_runs.test_plan_id')
            ->where('test_plans.project_id', $project->project_id)
            ->selectRaw('test_case_test_run.test_case_id, MAX(test_runs.id) as latest_run_id')
            ->groupBy('test_case_test_run.test_case_id');
    }

    private function latestRunIdsForPlan(TestPlan $testPlan): \Illuminate\Database\Query\Builder
    {
        return DB::table('test_case_test_run')
            ->join('test_runs', 'test_runs.id', '=', 'test_case_test_run.test_run_id')
            ->where('test_runs.test_plan_id', $testPlan->id)
            ->selectRaw('test_case_test_run.test_case_id, MAX(test_runs.id) as latest_run_id')
            ->groupBy('test_case_test_run.test_case_id');
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
