<script module lang="ts">
    import { index as testExecutionsIndexRoute } from '@/routes/test-executions';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Test Executions',
                href: testExecutionsIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import AutomationRunsPanel from '@/components/test-executions/AutomationRunsPanel.svelte';
    import { Spinner } from '@/components/ui/spinner';
    import { updateTestRunCaseStatus } from '@/lib/api/testRuns';
    import type {
        TestRunCase,
        TestRunCaseStatus,
        TestRunExecutionSummary,
        TestRunItem,
        TestRunPriority,
        TestRunStatus,
    } from '@/lib/api/testRuns';
    import { index as testExecutionsIndex } from '@/routes/test-executions';
    import { show as testPlansShow } from '@/routes/test-plans';

    let {
        initialTestRun,
    }: {
        initialTestRun: TestRunItem;
    } = $props();

    const CASE_STATUSES: TestRunCaseStatus[] = [
        'Not Run',
        'Passed',
        'Failed',
        'In Testing',
        'Skipped',
        'Blocked',
    ];

    const cloneTestRun = (testRun: TestRunItem): TestRunItem => ({
        ...testRun,
        execution_summary: { ...testRun.execution_summary },
        test_plan: {
            ...testRun.test_plan,
            project: { ...testRun.test_plan.project },
        },
        test_cases: testRun.test_cases?.map((testCase) => ({
            ...testCase,
            module: { ...testCase.module },
        })),
    });

    const startingState = (): TestRunItem => cloneTestRun(initialTestRun);

    let testRun = $state(startingState());
    let updatingCaseId = $state<number | null>(null);

    const canUpdateExecutionStatuses = $derived(testRun.status !== 'New');

    const buildSummary = (
        testCases: TestRunCase[] = [],
    ): TestRunExecutionSummary => {
        const summary: TestRunExecutionSummary = {
            not_run: 0,
            passed: 0,
            failed: 0,
            in_testing: 0,
            skipped: 0,
            blocked: 0,
            completed: 0,
            total: testCases.length,
            progress: 0,
        };

        testCases.forEach((testCase) => {
            if (testCase.execution_status === 'Not Run') {
                summary.not_run += 1;
            } else if (testCase.execution_status === 'Passed') {
                summary.passed += 1;
            } else if (testCase.execution_status === 'Failed') {
                summary.failed += 1;
            } else if (testCase.execution_status === 'In Testing') {
                summary.in_testing += 1;
            } else if (testCase.execution_status === 'Skipped') {
                summary.skipped += 1;
            } else if (testCase.execution_status === 'Blocked') {
                summary.blocked += 1;
            }
        });

        summary.completed = summary.passed;
        summary.progress =
            summary.total === 0
                ? 0
                : Math.round((summary.passed / summary.total) * 100);

        return summary;
    };

    const withCaseStatus = (
        source: TestRunItem,
        testCaseId: number,
        status: TestRunCaseStatus,
    ): TestRunItem => {
        const next = cloneTestRun(source);

        next.test_cases = next.test_cases?.map((testCase) =>
            testCase.id === testCaseId
                ? { ...testCase, execution_status: status }
                : testCase,
        );
        next.execution_summary = buildSummary(next.test_cases ?? []);

        if (
            next.execution_summary.total > 0 &&
            next.execution_summary.passed === next.execution_summary.total
        ) {
            next.status = 'Completed';
        } else if (next.status === 'Completed') {
            next.status = 'In Progress';
        }

        return next;
    };

    const statusClass = (status: TestRunStatus): string => {
        const classes: Record<TestRunStatus, string> = {
            New: 'border-slate-200 bg-slate-100 text-slate-700',
            'In Progress': 'border-sky-200 bg-sky-50 text-sky-700',
            Completed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        };

        return classes[status];
    };

    const priorityClass = (priority: TestRunPriority): string => {
        const classes: Record<TestRunPriority, string> = {
            Low: 'border-slate-200 bg-slate-100 text-slate-700',
            Medium: 'border-amber-200 bg-amber-100 text-amber-800',
            High: 'border-rose-200 bg-rose-100 text-rose-800',
            Urgent: 'border-red-300 bg-red-100 text-red-800',
        };

        return classes[priority];
    };

    const caseStatusClass = (status: TestRunCaseStatus): string => {
        const classes: Record<TestRunCaseStatus, string> = {
            'Not Run': 'border-slate-200 bg-slate-100 text-slate-700',
            Passed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            Failed: 'border-rose-200 bg-rose-50 text-rose-700',
            'In Testing': 'border-sky-200 bg-sky-50 text-sky-700',
            Skipped: 'border-amber-200 bg-amber-50 text-amber-700',
            Blocked: 'border-orange-200 bg-orange-50 text-orange-700',
        };

        return classes[status];
    };

    const formatDateTime = (value: string | null): string => {
        if (!value) {
            return 'Not available';
        }

        return new Intl.DateTimeFormat('en', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    };

    const formatExecutionDate = (
        value: string | null,
        fallback: string,
    ): string => (value ? formatDateTime(value) : fallback);

    const updateCaseStatus = async (
        testCaseId: number,
        status: TestRunCaseStatus,
    ): Promise<void> => {
        const currentCase = testRun.test_cases?.find(
            (testCase) => testCase.id === testCaseId,
        );

        if (!currentCase || currentCase.execution_status === status) {
            return;
        }

        if (!canUpdateExecutionStatuses) {
            toast.error(
                'Start this test run before updating execution statuses.',
            );

            return;
        }

        const previous = cloneTestRun(testRun);

        updatingCaseId = testCaseId;
        testRun = withCaseStatus(testRun, testCaseId, status);

        try {
            const response = await updateTestRunCaseStatus(
                testRun.id,
                testCaseId,
                status,
            );

            testRun = cloneTestRun(response.data);
            toast.success('Test case status updated.');
        } catch (error) {
            testRun = previous;
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to update the test case status.',
            );
        } finally {
            updatingCaseId = null;
        }
    };

    const summaryCards = $derived([
        {
            title: 'Passed',
            value: testRun.execution_summary.passed,
            icon: CircleCheck,
            iconClass: 'bg-emerald-50 text-emerald-700',
        },
        {
            title: 'Failed',
            value: testRun.execution_summary.failed,
            icon: CircleAlert,
            iconClass: 'bg-rose-50 text-rose-700',
        },
        {
            title: 'Not Run',
            value: testRun.execution_summary.not_run,
            icon: Clock3,
            iconClass: 'bg-slate-100 text-slate-700',
        },
        {
            title: 'Pass Progress',
            value: `${testRun.execution_summary.completed}/${testRun.execution_summary.total}`,
            icon: ShieldCheck,
            iconClass: 'bg-sky-50 text-sky-700',
        },
    ]);
</script>

<AppHead title={testRun.name} />

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div
            class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="min-w-0">
                <Link
                    href={testExecutionsIndex()}
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition-colors hover:text-[#002b57]"
                >
                    <ArrowLeft class="size-4" />
                    Back to Test Executions
                </Link>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1
                        class="text-3xl font-bold tracking-normal text-[#071a2f]"
                    >
                        {testRun.name}
                    </h1>
                    <span
                        class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${statusClass(
                            testRun.status,
                        )}`}
                    >
                        {testRun.status}
                    </span>
                    <span
                        class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${priorityClass(
                            testRun.priority,
                        )}`}
                    >
                        {testRun.priority}
                    </span>
                </div>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    {testRun.description ??
                        'No execution context has been added to this run yet.'}
                </p>
            </div>

            <div
                class="w-full rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:max-w-sm"
            >
                <div class="flex items-center justify-between text-sm">
                    <span class="font-semibold text-[#071a2f]"
                        >Run Progress</span
                    >
                    <span class="font-bold text-[#002b57]">
                        {testRun.execution_summary.progress}%
                    </span>
                </div>
                <div class="mt-3 h-2.5 rounded-full bg-slate-100">
                    <div
                        class="h-2.5 rounded-full bg-emerald-500 transition-all"
                        style={`width: ${testRun.execution_summary.progress}%`}
                    ></div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-600">
                    <p>
                        <span class="font-semibold text-[#071a2f]">Tester:</span
                        >
                        {testRun.tester_name}
                    </p>
                    <p>
                        <span class="font-semibold text-[#071a2f]">Env:</span>
                        {testRun.environment}
                    </p>
                    <p class="col-span-2">
                        <span class="font-semibold text-[#071a2f]">
                            Completed Execution Date:
                        </span>
                        {formatExecutionDate(
                            testRun.execution_completed_at,
                            'Not completed',
                        )}
                    </p>
                </div>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {#each summaryCards as card (card.title)}
                <article
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold uppercase text-slate-500"
                            >
                                {card.title}
                            </p>
                            <p class="mt-2 text-2xl font-bold text-[#071a2f]">
                                {card.value}
                            </p>
                        </div>
                        <span
                            class={`flex size-9 items-center justify-center rounded-lg ${card.iconClass}`}
                        >
                            <card.icon class="size-5" />
                        </span>
                    </div>
                </article>
            {/each}
        </section>

        <AutomationRunsPanel testRunId={testRun.id} />

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-5 px-5 py-5 md:grid-cols-4">
                <article>
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Test Plan
                    </h2>
                    <Link
                        href={testPlansShow(testRun.test_plan.id)}
                        class="mt-2 inline-flex text-sm font-medium text-[#002b57] hover:underline"
                    >
                        {testRun.test_plan.name ?? 'Untitled plan'}
                    </Link>
                </article>
                <article>
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Project
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {testRun.test_plan.project.name ?? 'No project'}
                    </p>
                </article>
                <article>
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Start Execution Date
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {formatExecutionDate(
                            testRun.execution_started_at,
                            'Not started',
                        )}
                    </p>
                </article>
                <article>
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Selected Cases
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {testRun.execution_summary.total}
                    </p>
                </article>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-6"
            >
                <div>
                    <h2 class="text-base font-semibold text-[#071a2f]">
                        Execution Worksheet
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Update each selected case status for this run.
                    </p>
                    {#if !canUpdateExecutionStatuses}
                        <p class="mt-2 text-sm font-medium text-amber-700">
                            Start this test run before updating execution
                            statuses.
                        </p>
                    {/if}
                </div>
                <div
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                >
                    <ClipboardCheck class="size-4 text-[#002b57]" />
                    {testRun.execution_summary.passed} passed of {testRun
                        .execution_summary.total}
                </div>
            </div>

            <div class="overflow-x-auto">
                <table
                    id="test-run-cases-table"
                    aria-label="Test cases in this execution run"
                    class="min-w-full divide-y divide-slate-200"
                >
                    <thead class="bg-slate-50/80">
                        <tr
                            class="text-left text-xs font-semibold uppercase text-slate-500"
                        >
                            <th class="px-4 py-4 lg:px-6">Case</th>
                            <th class="px-4 py-4">Test Type</th>
                            <th class="px-4 py-4">Module</th>
                            <th class="px-4 py-4">Test Behavior</th>
                            <th class="px-4 py-4">Execution Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        {#if !testRun.test_cases || testRun.test_cases.length === 0}
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    No test cases are selected for this run.
                                </td>
                            </tr>
                        {:else}
                            {#each testRun.test_cases as testCase (testCase.id)}
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-5 lg:px-6">
                                        <div class="space-y-1">
                                            <p
                                                class="max-w-sm font-semibold text-[#071a2f]"
                                            >
                                                {testCase.case_key}, {testCase.title}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                Priority {testCase.priority}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-5">
                                        <span
                                            class="inline-flex rounded-md bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700"
                                        >
                                            {testCase.test_type}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-5 text-sm text-slate-600"
                                    >
                                        {testCase.module.name ??
                                            'Uncategorized'}
                                    </td>
                                    <td class="px-4 py-5">
                                        <span
                                            class="inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                        >
                                            {testCase.test_behavior}
                                        </span>
                                    </td>
                                    <td class="px-4 py-5">
                                        <div class="flex items-center gap-2">
                                            <select
                                                id={`test-run-case-${testCase.id}-status`}
                                                aria-label={`Execution status for ${testCase.case_key}`}
                                                class={`h-9 min-w-32 rounded-md border px-3 text-sm font-semibold outline-none transition-colors focus:ring-3 focus:ring-[#002b57]/15 ${caseStatusClass(
                                                    testCase.execution_status,
                                                )}`}
                                                value={testCase.execution_status}
                                                disabled={updatingCaseId ===
                                                    testCase.id ||
                                                    !canUpdateExecutionStatuses}
                                                onchange={(event) =>
                                                    updateCaseStatus(
                                                        testCase.id,
                                                        (
                                                            event.currentTarget as HTMLSelectElement
                                                        )
                                                            .value as TestRunCaseStatus,
                                                    )}
                                            >
                                                {#each CASE_STATUSES as status (status)}
                                                    <option value={status}>
                                                        {status}
                                                    </option>
                                                {/each}
                                            </select>
                                            {#if updatingCaseId === testCase.id}
                                                <Spinner />
                                            {/if}
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
