<?php

namespace App\Services;

use App\Ai\Agents\SeniorQualityAssuranceAgent;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class AiGenerationService
{
    private const REPORT_EXPORT_PROMPT = 'Create a leadership-ready project completion report from the supplied QA snapshot. Summarize current quality health, rank risks using only the available evidence, and recommend the most effective prioritized actions to complete the project.';

    public function __construct(
        public ReportService $reportService,
        public TestCaseGenerationCountResolver $testCaseGenerationCountResolver,
    ) {}

    /**
     * @param  array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>}  $filters
     * @return array{draft: array<string, mixed>, warnings: array<int, string>}
     */
    public function generateReportSummary(User $user, Project $project, array $filters): array
    {
        $result = $this->generate($user, [
            'artifact_type' => 'report_summary',
            'prompt' => self::REPORT_EXPORT_PROMPT,
            'report_filters' => $filters,
        ], $project);

        return [
            'draft' => $result['draft'],
            'warnings' => $result['warnings'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{draft: array<string, mixed>, warnings: array<int, string>, source_context: array<string, mixed>}
     */
    public function generate(User $user, array $data, ?Project $activeProject): array
    {
        $artifactType = (string) $data['artifact_type'];
        $project = $this->resolveProject($user, $artifactType, $data, $activeProject);
        $selection = $this->resolveSelection($project, $artifactType, $data);
        $context = $this->buildContext($project, $artifactType, $data, $selection);
        $prompt = trim((string) $data['prompt']);
        $countResolution = $this->resolveGenerationCount($artifactType, $data, $prompt);
        $maximumTestCaseCount = $this->testCaseGenerationCountResolver->maximum();

        $response = SeniorQualityAssuranceAgent::make(
            artifactType: $artifactType,
            context: $context,
            requestedCount: $countResolution['effective'],
            maximumTestCaseCount: $maximumTestCaseCount,
        )->prompt($prompt);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The QA agent did not return structured output.');
        }

        $structured = $response->structured;
        $agentWarnings = collect($this->arrayItems($structured['warnings'] ?? null))
            ->map(fn (mixed $warning): string => trim((string) $warning))
            ->filter()
            ->values()
            ->all();

        [$draft, $normalizationWarnings] = $this->normalizeDraft(
            $artifactType,
            is_array($structured['draft'] ?? null) ? $structured['draft'] : [],
            $project,
            $selection,
            $user,
            $countResolution['effective'],
            $maximumTestCaseCount,
        );

        return [
            'draft' => $draft,
            'warnings' => array_values(array_unique([
                ...$countResolution['warnings'],
                ...$agentWarnings,
                ...$normalizationWarnings,
            ])),
            'source_context' => $this->buildSourceContext(
                $artifactType,
                $project,
                $selection,
                $draft,
                $countResolution['requested'],
                $maximumTestCaseCount,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProject(
        User $user,
        string $artifactType,
        array $data,
        ?Project $activeProject,
    ): Project {
        $requestedProjectId = isset($data['project_id']) ? (int) $data['project_id'] : null;

        if ($artifactType === 'test_plan') {
            $project = $requestedProjectId
                ? $user->accessibleProjects()->whereKey($requestedProjectId)->first()
                : $activeProject;

            if (! $project instanceof Project) {
                throw ValidationException::withMessages([
                    'project_id' => 'Select a project before generating a test plan draft.',
                ]);
            }

            return $project;
        }

        if (! $activeProject instanceof Project) {
            throw ValidationException::withMessages([
                'project' => 'Select an active project before generating AI drafts.',
            ]);
        }

        if ($requestedProjectId !== null && $requestedProjectId !== $activeProject->project_id) {
            throw ValidationException::withMessages([
                'project_id' => 'AI generation must use the active project for this artifact.',
            ]);
        }

        return $activeProject;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}
     */
    private function resolveSelection(Project $project, string $artifactType, array $data): array
    {
        $testPlan = null;
        $testRun = null;
        $module = null;
        $errors = [];

        if (isset($data['test_plan_id'])) {
            $testPlan = $project->testPlans()
                ->whereKey((int) $data['test_plan_id'])
                ->first();

            if (! $testPlan instanceof TestPlan) {
                $errors['test_plan_id'] = 'The selected test plan does not belong to this project.';
            }
        }

        if (isset($data['test_run_id'])) {
            $testRun = TestRun::query()
                ->whereKey((int) $data['test_run_id'])
                ->whereHas('testPlan', function ($query) use ($project): void {
                    $query->where('project_id', $project->project_id);
                })
                ->first();

            if (! $testRun instanceof TestRun) {
                $errors['test_run_id'] = 'The selected test run does not belong to this project.';
            } elseif ($testPlan instanceof TestPlan && $testRun->test_plan_id !== $testPlan->id) {
                $errors['test_run_id'] = 'The selected test run does not belong to the chosen test plan.';
            } elseif (! $testPlan instanceof TestPlan) {
                $testPlan = $testRun->testPlan()->first();
            }
        }

        if (isset($data['module_id'])) {
            $module = TestRepositoryModule::query()
                ->where('project_id', $project->project_id)
                ->whereKey((int) $data['module_id'])
                ->first();

            if (! $module instanceof TestRepositoryModule) {
                $errors['module_id'] = 'The selected module does not belong to this project.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'test_plan' => $testPlan,
            'test_run' => $testRun,
            'module' => $module,
            'report_filters' => $artifactType === 'report_summary'
                ? $this->resolveReportFilters($project, $data, $testPlan)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array<string, mixed>
     */
    private function buildContext(
        Project $project,
        string $artifactType,
        array $data,
        array $selection,
    ): array {
        return match ($artifactType) {
            'test_plan' => $this->testPlanContext($project),
            'test_cases' => $this->testCaseContext($project, $selection),
            'issues' => $this->issueContext($project, $selection),
            'report_summary' => $this->reportSummaryContext($project, $selection),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function normalizeDraft(
        string $artifactType,
        array $draft,
        Project $project,
        array $selection,
        User $user,
        ?int $requestedCount,
        int $maximumTestCaseCount,
    ): array {
        return match ($artifactType) {
            'test_plan' => $this->normalizeTestPlanDraft($draft),
            'test_cases' => $this->normalizeTestCaseDrafts(
                $draft,
                $project,
                $selection,
                $requestedCount ?? $maximumTestCaseCount,
            ),
            'issues' => $this->normalizeIssueDrafts($draft, $project, $selection, $user),
            'report_summary' => $this->normalizeReportSummaryDraft($draft),
            default => [[], ['Unsupported AI draft artifact.']],
        };
    }

    /**
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function buildSourceContext(
        string $artifactType,
        Project $project,
        array $selection,
        array $draft,
        ?int $requestedCount,
        int $maximumTestCaseCount,
    ): array {
        return [
            'artifact_type' => $artifactType,
            'project' => [
                'id' => $project->project_id,
                'name' => $project->project_name,
            ],
            'selected_test_plan' => $selection['test_plan'] instanceof TestPlan
                ? [
                    'id' => $selection['test_plan']->id,
                    'name' => $selection['test_plan']->name,
                ]
                : null,
            'selected_test_run' => $selection['test_run'] instanceof TestRun
                ? [
                    'id' => $selection['test_run']->id,
                    'name' => $selection['test_run']->name,
                ]
                : null,
            'selected_module' => $selection['module'] instanceof TestRepositoryModule
                ? [
                    'id' => $selection['module']->id,
                    'name' => $selection['module']->name,
                    'key_prefix' => $selection['module']->key_prefix,
                ]
                : null,
            'requested_count' => $requestedCount,
            'generated_count' => $artifactType === 'test_cases'
                ? count($this->arrayItems($draft['cases'] ?? null))
                : null,
            'generation_limit' => $artifactType === 'test_cases'
                ? $maximumTestCaseCount
                : null,
            'report_filters' => $selection['report_filters'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function testPlanContext(Project $project): array
    {
        $recentPlans = $project->testPlans()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'status', 'priority', 'scope', 'release_version', 'tester'])
            ->map(fn (TestPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'status' => $plan->status,
                'priority' => $plan->priority,
                'scope' => $plan->scope,
                'release_version' => $plan->release_version,
                'tester' => $plan->tester,
            ])
            ->all();

        return [
            'project' => [
                'id' => $project->project_id,
                'name' => $project->project_name,
                'description' => $project->project_description,
                'status' => $project->project_status,
            ],
            'allowed_values' => [
                'priorities' => TestPlan::priorities(),
                'status' => TestPlan::STATUS_DRAFT,
            ],
            'recent_test_plans' => $recentPlans,
        ];
    }

    /**
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array<string, mixed>
     */
    private function testCaseContext(Project $project, array $selection): array
    {
        $modules = TestRepositoryModule::query()
            ->where('project_id', $project->project_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'key_prefix'])
            ->map(fn (TestRepositoryModule $module): array => [
                'id' => $module->id,
                'parent_id' => $module->parent_id,
                'name' => $module->name,
                'key_prefix' => $module->key_prefix,
            ])
            ->all();

        $recentCases = TestCase::query()
            ->where('project_id', $project->project_id)
            ->when(
                $selection['module'] instanceof TestRepositoryModule,
                fn ($query) => $query->where('module_id', $selection['module']->id),
            )
            ->latest()
            ->limit(5)
            ->get(['id', 'module_id', 'case_key', 'title', 'priority', 'automation_status', 'test_behavior', 'test_type'])
            ->map(fn (TestCase $testCase): array => [
                'id' => $testCase->id,
                'module_id' => $testCase->module_id,
                'case_key' => $testCase->case_key,
                'title' => $testCase->title,
                'priority' => $testCase->priority,
                'automation_status' => $testCase->automation_status,
                'test_behavior' => $testCase->test_behavior,
                'test_type' => $testCase->test_type,
            ])
            ->all();

        return [
            'project' => [
                'id' => $project->project_id,
                'name' => $project->project_name,
            ],
            'selected_module' => $selection['module'] instanceof TestRepositoryModule
                ? [
                    'id' => $selection['module']->id,
                    'name' => $selection['module']->name,
                    'key_prefix' => $selection['module']->key_prefix,
                ]
                : null,
            'selected_test_plan' => $selection['test_plan'] instanceof TestPlan
                ? [
                    'id' => $selection['test_plan']->id,
                    'name' => $selection['test_plan']->name,
                    'scope' => $selection['test_plan']->scope,
                    'strategy' => $selection['test_plan']->strategy,
                    'criteria' => $selection['test_plan']->criteria,
                ]
                : null,
            'allowed_values' => [
                'priorities' => TestCase::priorities(),
                'automation_statuses' => TestCase::automationStatuses(),
                'behaviors' => TestCase::behaviors(),
                'types' => TestCase::types(),
            ],
            'modules' => $modules,
            'recent_test_cases' => $recentCases,
        ];
    }

    /**
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array<string, mixed>
     */
    private function issueContext(Project $project, array $selection): array
    {
        $recentIssues = $project->issues()
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'priority', 'severity', 'status', 'environment', 'defect_type'])
            ->map(fn (Issue $issue): array => [
                'id' => $issue->id,
                'title' => $issue->title,
                'priority' => $issue->priority,
                'severity' => $issue->severity,
                'status' => $issue->status,
                'environment' => $issue->environment,
                'defect_type' => $issue->defect_type,
            ])
            ->all();

        $testPlans = $project->testPlans()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TestPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])
            ->all();

        $testRuns = TestRun::query()
            ->whereHas('testPlan', fn ($query) => $query->where('project_id', $project->project_id))
            ->latest()
            ->limit(10)
            ->get(['id', 'test_plan_id', 'name', 'status'])
            ->map(fn (TestRun $run): array => [
                'id' => $run->id,
                'test_plan_id' => $run->test_plan_id,
                'name' => $run->name,
                'status' => $run->status,
            ])
            ->all();

        return [
            'project' => [
                'id' => $project->project_id,
                'name' => $project->project_name,
            ],
            'selected_test_plan' => $selection['test_plan'] instanceof TestPlan
                ? [
                    'id' => $selection['test_plan']->id,
                    'name' => $selection['test_plan']->name,
                ]
                : null,
            'selected_test_run' => $selection['test_run'] instanceof TestRun
                ? [
                    'id' => $selection['test_run']->id,
                    'name' => $selection['test_run']->name,
                    'status' => $selection['test_run']->status,
                ]
                : null,
            'allowed_values' => [
                'priorities' => Issue::priorities(),
                'severities' => Issue::severities(),
                'statuses' => Issue::statuses(),
                'environments' => Issue::environments(),
                'defect_types' => Issue::defectTypes(),
            ],
            'test_plans' => $testPlans,
            'test_runs' => $testRuns,
            'recent_issues' => $recentIssues,
        ];
    }

    /**
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array<string, mixed>
     */
    private function reportSummaryContext(Project $project, array $selection): array
    {
        /** @var array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>} $filters */
        $filters = $selection['report_filters'] ?? $this->defaultReportFilters();
        $payload = $this->reportService->getPayload($project, $filters);

        return [
            'project' => [
                'id' => $project->project_id,
                'name' => $project->project_name,
            ],
            'report_filters' => $filters,
            'report_snapshot' => $this->reportSnapshot($payload, $filters['sections']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $sections
     * @return array<string, mixed>
     */
    private function reportSnapshot(array $payload, array $sections): array
    {
        $snapshot = [];
        $sectionPayloads = [
            'metrics' => ['metrics', $payload['metrics']],
            'issue_trend' => ['issue_trend', $payload['trend']],
            'issue_distribution' => ['issue_distribution', $payload['distribution']],
            'result_summary' => ['test_result_summary', $payload['results']],
            'team_workload' => ['team_workload', array_slice($payload['workload'], 0, 5)],
            'recent_executions' => ['recent_executions', array_slice($payload['recentExecutions'], 0, 5)],
        ];

        foreach ($sections as $section) {
            if (! isset($sectionPayloads[$section])) {
                continue;
            }

            [$key, $value] = $sectionPayloads[$section];
            $snapshot[$key] = $value;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function normalizeTestPlanDraft(array $draft): array
    {
        $warnings = [];
        $priority = $this->normalizeEnum(
            $draft['priority'] ?? null,
            TestPlan::priorities(),
            TestPlan::PRIORITY_MEDIUM,
            'Priority was reset to Medium.',
            $warnings,
        );

        $startDate = $this->normalizeDate($draft['start_date'] ?? null);
        $dueDate = $this->normalizeDate($draft['due_date'] ?? null);

        if ($startDate && $dueDate && $dueDate < $startDate) {
            $dueDate = null;
            $warnings[] = 'Due date was cleared because it was earlier than the start date.';
        }

        return [[
            'name' => $this->requiredText($draft['name'] ?? null, 'Generated QA Test Plan'),
            'description' => $this->nullableText($draft['description'] ?? null),
            'scope' => $this->nullableText($draft['scope'] ?? null),
            'strategy' => $this->nullableText($draft['strategy'] ?? null),
            'criteria' => $this->nullableText($draft['criteria'] ?? null),
            'status' => TestPlan::STATUS_DRAFT,
            'priority' => $priority,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'release_version' => $this->nullableText($draft['release_version'] ?? null, 255),
            'tester' => $this->nullableText($draft['tester'] ?? null, 255),
        ], $warnings];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function normalizeTestCaseDrafts(
        array $draft,
        Project $project,
        array $selection,
        int $resultLimit,
    ): array {
        $warnings = [];
        $resultLimit = max(1, $resultLimit);
        $availableModuleIds = TestRepositoryModule::query()
            ->where('project_id', $project->project_id)
            ->pluck('id')
            ->all();
        $defaultModuleId = $selection['module'] instanceof TestRepositoryModule
            ? $selection['module']->id
            : ($availableModuleIds[0] ?? null);

        $caseDrafts = collect($this->arrayItems($draft['cases'] ?? null))
            ->filter(fn (mixed $case): bool => is_array($case))
            ->values();

        if ($caseDrafts->count() > $resultLimit) {
            $caseLabel = $resultLimit === 1 ? 'test case' : 'test cases';
            $warnings[] = 'The AI returned more than '.$resultLimit.' '.$caseLabel.', so extra drafts were removed.';
        }

        $cases = $caseDrafts
            ->take($resultLimit)
            ->map(function (array $case, int $index) use ($availableModuleIds, $defaultModuleId, &$warnings): array {
                $moduleId = isset($case['module_id']) ? (int) $case['module_id'] : null;

                if (! in_array($moduleId, $availableModuleIds, true)) {
                    $moduleId = $defaultModuleId;
                    $warnings[] = 'One or more generated test cases were assigned to the selected module because the AI returned an unavailable module.';
                }

                return [
                    'module_id' => $moduleId,
                    'title' => $this->requiredText($case['title'] ?? null, 'Generated Test Case '.($index + 1)),
                    'priority' => $this->normalizeEnum(
                        $case['priority'] ?? null,
                        TestCase::priorities(),
                        TestCase::PRIORITY_MEDIUM,
                        'A generated test case priority was reset to Medium.',
                        $warnings,
                    ),
                    'automation_status' => $this->normalizeEnum(
                        $case['automation_status'] ?? null,
                        TestCase::automationStatuses(),
                        TestCase::AUTOMATION_MANUAL,
                        'A generated automation status was reset to Manual.',
                        $warnings,
                    ),
                    'test_behavior' => $this->normalizeEnum(
                        $case['test_behavior'] ?? null,
                        TestCase::behaviors(),
                        TestCase::BEHAVIOR_POSITIVE,
                        'A generated test behavior was reset to Positive.',
                        $warnings,
                    ),
                    'test_type' => $this->normalizeEnum(
                        $case['test_type'] ?? null,
                        TestCase::types(),
                        TestCase::TYPE_FUNCTIONAL,
                        'A generated test type was reset to Functional.',
                        $warnings,
                    ),
                    'preconditions' => $this->nullableText($case['preconditions'] ?? null),
                    'steps' => collect($this->arrayItems($case['steps'] ?? null))
                        ->filter(fn (mixed $step): bool => is_array($step))
                        ->values()
                        ->map(fn (array $step): array => [
                            'action' => $this->requiredText($step['action'] ?? null, 'Review and complete this step.'),
                            'expected_result' => $this->requiredText($step['expected_result'] ?? null, 'Expected behavior should be confirmed.'),
                        ])
                        ->all(),
                ];
            })
            ->all();

        if ($cases === []) {
            $cases[] = [
                'module_id' => $defaultModuleId,
                'title' => 'Generated Test Case',
                'priority' => TestCase::PRIORITY_MEDIUM,
                'automation_status' => TestCase::AUTOMATION_MANUAL,
                'test_behavior' => TestCase::BEHAVIOR_POSITIVE,
                'test_type' => TestCase::TYPE_FUNCTIONAL,
                'preconditions' => null,
                'steps' => [],
            ];
            $warnings[] = 'The AI response did not contain any usable test cases, so a placeholder draft was returned.';
        }

        return [[
            'cases' => $cases,
        ], array_values(array_unique($warnings))];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array{test_plan: TestPlan|null, test_run: TestRun|null, module: TestRepositoryModule|null, report_filters: array<string, mixed>|null}  $selection
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function normalizeIssueDrafts(array $draft, Project $project, array $selection, User $user): array
    {
        $warnings = [];
        $selectedPlanId = $selection['test_plan'] instanceof TestPlan ? $selection['test_plan']->id : null;
        $selectedRunId = $selection['test_run'] instanceof TestRun ? $selection['test_run']->id : null;

        $issues = collect($this->arrayItems($draft['issues'] ?? null))
            ->filter(fn (mixed $issue): bool => is_array($issue))
            ->values()
            ->map(function (array $issue, int $index) use ($selectedPlanId, $selectedRunId, $user, &$warnings): array {
                $testPlanId = isset($issue['test_plan_id']) ? (int) $issue['test_plan_id'] : $selectedPlanId;
                $testRunId = isset($issue['test_run_id']) ? (int) $issue['test_run_id'] : $selectedRunId;

                if ($selectedPlanId !== null && $testPlanId !== $selectedPlanId) {
                    $warnings[] = 'A generated issue was re-linked to the selected test plan.';
                    $testPlanId = $selectedPlanId;
                }

                if ($selectedRunId !== null && $testRunId !== $selectedRunId) {
                    $warnings[] = 'A generated issue was re-linked to the selected test run.';
                    $testRunId = $selectedRunId;
                }

                return [
                    'title' => $this->requiredText($issue['title'] ?? null, 'Generated Issue '.($index + 1)),
                    'description' => $this->requiredText($issue['description'] ?? null, 'Describe the steps to reproduce this issue.'),
                    'expected_result' => $this->nullableText($issue['expected_result'] ?? null),
                    'actual_result' => $this->nullableText($issue['actual_result'] ?? null),
                    'priority' => $this->normalizeEnum(
                        $issue['priority'] ?? null,
                        Issue::priorities(),
                        Issue::PRIORITY_MEDIUM,
                        'A generated issue priority was reset to Medium.',
                        $warnings,
                    ),
                    'severity' => $this->normalizeEnum(
                        $issue['severity'] ?? null,
                        Issue::severities(),
                        Issue::SEVERITY_MEDIUM,
                        'A generated issue severity was reset to Medium.',
                        $warnings,
                    ),
                    'status' => Issue::STATUS_OPEN,
                    'reporter' => $this->requiredText($issue['reporter'] ?? null, $user->name, 255),
                    'assignee' => $this->nullableText($issue['assignee'] ?? null, 255),
                    'environment' => $this->normalizeOptionalEnum(
                        $issue['environment'] ?? null,
                        Issue::environments(),
                        'An invalid environment was removed from a generated issue.',
                        $warnings,
                    ),
                    'defect_type' => $this->normalizeOptionalEnum(
                        $issue['defect_type'] ?? null,
                        Issue::defectTypes(),
                        'An invalid defect type was removed from a generated issue.',
                        $warnings,
                    ),
                    'test_plan_id' => $testPlanId,
                    'test_run_id' => $testRunId,
                ];
            })
            ->all();

        if ($issues === []) {
            $issues[] = [
                'title' => 'Generated Issue',
                'description' => 'Describe the steps to reproduce this issue.',
                'expected_result' => null,
                'actual_result' => null,
                'priority' => Issue::PRIORITY_MEDIUM,
                'severity' => Issue::SEVERITY_MEDIUM,
                'status' => Issue::STATUS_OPEN,
                'reporter' => $user->name,
                'assignee' => null,
                'environment' => null,
                'defect_type' => null,
                'test_plan_id' => $selectedPlanId,
                'test_run_id' => $selectedRunId,
            ];
            $warnings[] = 'The AI response did not contain any usable issues, so a placeholder draft was returned.';
        }

        return [[
            'issues' => $issues,
        ], array_values(array_unique($warnings))];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function normalizeReportSummaryDraft(array $draft): array
    {
        return [[
            'executive_summary' => $this->requiredText($draft['executive_summary'] ?? null, 'QA summary is ready for review.', 2000),
            'quality_risks' => $this->stringList($draft['quality_risks'] ?? []),
            'coverage_notes' => $this->stringList($draft['coverage_notes'] ?? []),
            'issue_analysis' => $this->stringList($draft['issue_analysis'] ?? []),
            'recommendations' => $this->stringList($draft['recommendations'] ?? []),
            'next_actions' => $this->stringList($draft['next_actions'] ?? []),
        ], []];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>}
     */
    private function resolveReportFilters(Project $project, array $data, ?TestPlan $selectedPlan): array
    {
        $sectionValues = collect($this->reportService->sections())
            ->pluck('value')
            ->values()
            ->all();

        /** @var array<string, mixed> $reportFilters */
        $reportFilters = is_array($data['report_filters'] ?? null)
            ? $data['report_filters']
            : [];
        $range = in_array((string) ($reportFilters['range'] ?? '7d'), ['7d', '30d', 'custom'], true)
            ? (string) ($reportFilters['range'] ?? '7d')
            : '7d';
        $grouping = in_array((string) ($reportFilters['grouping'] ?? 'daily'), ['daily', 'weekly'], true)
            ? (string) ($reportFilters['grouping'] ?? 'daily')
            : 'daily';

        $endDate = Carbon::today();
        $startDate = $range === '30d'
            ? $endDate->copy()->subDays(29)
            : $endDate->copy()->subDays(6);

        if (
            $range === 'custom'
            && filled($reportFilters['start_date'] ?? null)
            && filled($reportFilters['end_date'] ?? null)
        ) {
            $startDate = Carbon::parse((string) $reportFilters['start_date']);
            $endDate = Carbon::parse((string) $reportFilters['end_date']);
        }

        $planId = $selectedPlan?->id;

        if ($planId === null && is_numeric($reportFilters['scope'] ?? null)) {
            $planId = (int) $reportFilters['scope'];

            $selectedPlan = $project->testPlans()->whereKey($planId)->first();

            if (! $selectedPlan instanceof TestPlan) {
                throw ValidationException::withMessages([
                    'report_filters.scope' => 'The selected report scope does not belong to this project.',
                ]);
            }
        }

        $sections = collect($this->arrayItems($reportFilters['sections'] ?? $sectionValues))
            ->filter(fn (mixed $section): bool => is_string($section) && in_array($section, $sectionValues, true))
            ->values()
            ->all();

        if ($sections === []) {
            $sections = $sectionValues;
        }

        return [
            'scope' => $planId === null ? 'all' : (string) $planId,
            'plan_id' => $planId,
            'range' => $range,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'grouping' => $grouping,
            'sections' => $sections,
        ];
    }

    /**
     * @return array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>}
     */
    private function defaultReportFilters(): array
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(6);
        $sections = collect($this->reportService->sections())
            ->pluck('value')
            ->values()
            ->all();

        return [
            'scope' => 'all',
            'plan_id' => null,
            'range' => '7d',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'grouping' => 'daily',
            'sections' => $sections,
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayItems(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{requested: int|null, effective: int|null, warnings: array<int, string>}
     */
    private function resolveGenerationCount(string $artifactType, array $data, string $prompt): array
    {
        if ($artifactType === 'test_cases') {
            $resolution = $this->testCaseGenerationCountResolver->resolve($prompt);
            $warnings = [];

            if ($resolution['was_limited'] && $resolution['requested'] !== null) {
                $warnings[] = $resolution['requested'] > $this->testCaseGenerationCountResolver->maximum()
                    ? 'A request for '.$resolution['requested'].' test cases exceeds the generation limit of '.$this->testCaseGenerationCountResolver->maximum().', so the request was capped.'
                    : 'At least one test case must be generated, so the request was raised to one.';
            }

            return [
                'requested' => $resolution['requested'],
                'effective' => $resolution['effective'],
                'warnings' => $warnings,
            ];
        }

        $count = $artifactType === 'issues' && isset($data['count'])
            ? max(1, min(10, (int) $data['count']))
            : 1;

        return [
            'requested' => $count,
            'effective' => $count,
            'warnings' => [],
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $allowedValues
     * @param  array<int, string>  $warnings
     */
    private function normalizeEnum(
        mixed $value,
        array $allowedValues,
        string $fallback,
        string $warning,
        array &$warnings,
    ): string {
        $normalized = trim((string) ($value ?? ''));

        if (in_array($normalized, $allowedValues, true)) {
            return $normalized;
        }

        $warnings[] = $warning;

        return $fallback;
    }

    /**
     * @param  array<int, string>  $allowedValues
     * @param  array<int, string>  $warnings
     */
    private function normalizeOptionalEnum(
        mixed $value,
        array $allowedValues,
        string $warning,
        array &$warnings,
    ): ?string {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, $allowedValues, true)) {
            return $normalized;
        }

        $warnings[] = $warning;

        return null;
    }

    private function requiredText(mixed $value, string $fallback, int $limit = 255): string
    {
        $normalized = $this->nullableText($value, $limit);

        return $normalized ?? $fallback;
    }

    private function nullableText(mixed $value, int $limit = 5000): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, $limit, '');
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value, int $limit = 5): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->map(fn (string $item): string => Str::limit($item, 500, ''))
            ->take($limit)
            ->values()
            ->all();
    }
}
