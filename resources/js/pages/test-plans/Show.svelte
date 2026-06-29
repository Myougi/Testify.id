<script module lang="ts">
    import { index as testPlansIndexRoute } from '@/routes/test-plans';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Test Plans',
                href: testPlansIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Download from 'lucide-svelte/icons/download';
    import Eye from 'lucide-svelte/icons/eye';
    import FolderKanban from 'lucide-svelte/icons/folder-kanban';
    import ListChecks from 'lucide-svelte/icons/list-checks';
    import Plus from 'lucide-svelte/icons/plus';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import CaseSelector from '@/components/test-plans/CaseSelector.svelte';
    import PlanStatusBadge from '@/components/test-plans/PlanStatusBadge.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Spinner } from '@/components/ui/spinner';
    import {
        attachTestCasesToPlan,
        detachTestCaseFromPlan,
        exportTestPlanPdf,
        fetchAvailableTestCases,
    } from '@/lib/api/testPlans';
    import type {
        AvailableCaseFilters,
        AvailableTestCasesResponse,
        TestPlanItem,
    } from '@/lib/api/testPlans';
    import type {
        TestPlanRunsResponse,
        TestRunPriority,
        TestRunStatus,
    } from '@/lib/api/testRuns';
    import { show as testExecutionShow } from '@/routes/test-executions';
    import { index as testPlansIndex } from '@/routes/test-plans';

    let {
        initialTestPlan,
        initialAvailableCases,
        initialTestRuns,
    }: {
        initialTestPlan: TestPlanItem;
        initialAvailableCases: AvailableTestCasesResponse;
        initialTestRuns: TestPlanRunsResponse;
    } = $props();

    const cloneTestPlan = (testPlan: TestPlanItem): TestPlanItem => ({
        ...testPlan,
        project: { ...testPlan.project },
        test_cases: testPlan.test_cases?.map((testCase) => ({
            ...testCase,
            module: { ...testCase.module },
        })),
    });

    const cloneAvailableCases = (
        payload: AvailableTestCasesResponse,
    ): AvailableTestCasesResponse => ({
        ...payload,
        data: payload.data.map((testCase) => ({
            ...testCase,
            module: { ...testCase.module },
            project: { ...testCase.project },
        })),
        meta: {
            ...payload.meta,
            links: payload.meta.links.map((link) => ({ ...link })),
        },
        filters: {
            ...payload.filters,
        },
        options: {
            modules: payload.options.modules.map((module) => ({ ...module })),
            priorities: [...payload.options.priorities],
            automationStatuses: [...payload.options.automationStatuses],
            behaviors: [...payload.options.behaviors],
            types: [...payload.options.types],
        },
    });

    const cloneTestRuns = (
        payload: TestPlanRunsResponse,
    ): TestPlanRunsResponse => ({
        ...payload,
        data: payload.data.map((testRun) => ({
            ...testRun,
            test_plan: {
                ...testRun.test_plan,
                project: { ...testRun.test_plan.project },
            },
            test_cases: testRun.test_cases?.map((testCase) => ({
                ...testCase,
                module: { ...testCase.module },
            })),
        })),
        meta: {
            ...payload.meta,
            links: payload.meta.links.map((link) => ({ ...link })),
        },
    });

    const createStartingState = (): {
        testPlan: TestPlanItem;
        availablePayload: AvailableTestCasesResponse;
        testRuns: TestPlanRunsResponse;
    } => ({
        testPlan: cloneTestPlan(initialTestPlan),
        availablePayload: cloneAvailableCases(initialAvailableCases),
        testRuns: cloneTestRuns(initialTestRuns),
    });

    const startingState = createStartingState();

    let testPlan = $state(startingState.testPlan);
    let availablePayload = $state(startingState.availablePayload);
    let testRuns = $state(startingState.testRuns);
    let availableFilters = $state<AvailableCaseFilters>({
        ...startingState.availablePayload.filters,
    });
    let addCasesOpen = $state(false);
    let selectedCaseIds = $state<number[]>([]);
    let availableLoading = $state(false);
    let assigning = $state(false);
    let removingCaseId = $state<number | null>(null);
    let exportingPdf = $state(false);

    const assignedCaseCount = $derived(
        testPlan.test_cases?.length ?? testPlan.assigned_test_cases_count ?? 0,
    );

    const summaryCards = $derived([
        {
            title: 'Project',
            value: testPlan.project.name,
            icon: FolderKanban,
        },
        {
            title: 'Assigned Cases',
            value: String(assignedCaseCount),
            icon: ListChecks,
        },
        {
            title: 'Release',
            value: testPlan.release_version ?? 'Not set',
            icon: ShieldCheck,
        },
    ]);

    const statusClass = (status: TestRunStatus): string => {
        const classes: Record<TestRunStatus, string> = {
            New: 'bg-slate-100 text-slate-700',
            'In Progress': 'bg-sky-50 text-sky-700',
            Completed: 'bg-emerald-50 text-emerald-700',
        };

        return classes[status];
    };

    const priorityClass = (priority: TestRunPriority): string => {
        const classes: Record<TestRunPriority, string> = {
            Low: 'bg-slate-100 text-slate-700',
            Medium: 'bg-amber-50 text-amber-700',
            High: 'bg-rose-50 text-rose-700',
            Urgent: 'bg-red-100 text-red-800',
        };

        return classes[priority];
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

    const syncAvailablePayload = (
        response: AvailableTestCasesResponse,
    ): void => {
        availablePayload = cloneAvailableCases(response);
        availableFilters = {
            ...response.filters,
        };
    };

    const updatePlan = (nextPlan: TestPlanItem): void => {
        testPlan = cloneTestPlan(nextPlan);
    };

    const reloadAvailable = async (
        nextFilters: Partial<AvailableCaseFilters> = {},
    ): Promise<void> => {
        availableLoading = true;

        try {
            const response = await fetchAvailableTestCases(testPlan.id, {
                ...availableFilters,
                ...nextFilters,
            });

            syncAvailablePayload(response);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load available test cases.',
            );
        } finally {
            availableLoading = false;
        }
    };

    const toggleSelectedCase = (testCaseId: number): void => {
        if (selectedCaseIds.includes(testCaseId)) {
            selectedCaseIds = selectedCaseIds.filter((id) => id !== testCaseId);

            return;
        }

        selectedCaseIds = [...selectedCaseIds, testCaseId];
    };

    const toggleVisibleCases = (
        testCaseIds: number[],
        checked: boolean,
    ): void => {
        if (checked) {
            selectedCaseIds = Array.from(
                new Set([...selectedCaseIds, ...testCaseIds]),
            );

            return;
        }

        selectedCaseIds = selectedCaseIds.filter(
            (selectedCaseId) => !testCaseIds.includes(selectedCaseId),
        );
    };

    const addSelectedCases = async (): Promise<void> => {
        if (selectedCaseIds.length === 0) {
            return;
        }

        assigning = true;

        try {
            const response = await attachTestCasesToPlan(
                testPlan.id,
                selectedCaseIds,
            );

            updatePlan(response.data);
            selectedCaseIds = [];
            toast.success(response.message);
            await reloadAvailable({ page: 1 });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to add test cases to the plan.',
            );
        } finally {
            assigning = false;
        }
    };

    const removeAssignedCase = async (testCaseId: number): Promise<void> => {
        removingCaseId = testCaseId;

        try {
            const response = await detachTestCaseFromPlan(
                testPlan.id,
                testCaseId,
            );

            updatePlan(response.data);
            toast.success(response.message);
            await reloadAvailable({ page: 1 });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to remove the test case from the plan.',
            );
        } finally {
            removingCaseId = null;
        }
    };

    const hasMethodology = $derived(
        Boolean(testPlan.scope || testPlan.strategy || testPlan.criteria),
    );

    const exportPdf = async (): Promise<void> => {
        if (exportingPdf) {
            return;
        }

        exportingPdf = true;

        try {
            const download = await exportTestPlanPdf(testPlan.id);
            const downloadUrl = URL.createObjectURL(download.blob);
            const link = document.createElement('a');

            link.href = downloadUrl;
            link.download =
                download.filename ?? `testify-${testPlan.id}-test-plan.pdf`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to export the test plan PDF.',
            );
        } finally {
            exportingPdf = false;
        }
    };
</script>

<AppHead title={testPlan.name} />

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <Link
                    href={testPlansIndex()}
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition-colors hover:text-[#002b57]"
                >
                    <ArrowLeft class="size-4" />
                    Back to Test Plans
                </Link>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1
                        class="text-3xl font-bold tracking-normal text-[#071a2f]"
                    >
                        {testPlan.name}
                    </h1>
                    <PlanStatusBadge status={testPlan.status} />
                </div>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    {testPlan.description ??
                        'No description has been added to this test plan yet.'}
                </p>
            </div>

            <div class="flex flex-col items-stretch gap-3 sm:items-end">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 gap-2 border-slate-300 bg-white text-[#071a2f] hover:bg-slate-50"
                    disabled={exportingPdf}
                    onclick={exportPdf}
                >
                    {#if exportingPdf}
                        <Spinner class="size-4" />
                        Generating PDF...
                    {:else}
                        <Download class="size-4" />
                        Export PDF
                    {/if}
                </Button>

                <div
                    class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm"
                >
                    <p>
                        <span class="font-semibold text-[#071a2f]">Tester:</span
                        >
                        {testPlan.tester ?? 'Unassigned'}
                    </p>
                    <p class="mt-1">
                        <span class="font-semibold text-[#071a2f]"
                            >Timeline:</span
                        >
                        {testPlan.start_date ?? 'Not set'} to {testPlan.due_date ??
                            'Not set'}
                    </p>
                </div>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-3">
            {#each summaryCards as card (card.title)}
                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500"
                            >
                                {card.title}
                            </p>
                            <p class="mt-3 text-2xl font-bold text-[#071a2f]">
                                {card.value}
                            </p>
                        </div>
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-[#eff5fb] text-[#002b57]"
                        >
                            <card.icon class="size-5" />
                        </span>
                    </div>
                </article>
            {/each}
        </section>

        {#if hasMethodology}
            <section
                class="rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="grid gap-6 px-5 py-5 lg:grid-cols-3">
                    <article>
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Scope
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {testPlan.scope ?? 'No scope has been documented.'}
                        </p>
                    </article>
                    <article>
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Strategy
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {testPlan.strategy ??
                                'No strategy has been documented.'}
                        </p>
                    </article>
                    <article>
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Criteria
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {testPlan.criteria ??
                                'No entry or exit criteria have been documented.'}
                        </p>
                    </article>
                </div>
            </section>
        {/if}

        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6"
            >
                <div>
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Assigned Test Cases
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Cases currently scheduled under this plan.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700"
                    >
                        {assignedCaseCount}
                    </span>
                    <Button
                        type="button"
                        class="bg-[#002b57] text-white hover:bg-[#003c78]"
                        onclick={() => (addCasesOpen = true)}
                    >
                        <Plus class="size-4" />
                        Add Test Cases
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table
                    id="test-plan-assigned-cases-table"
                    aria-label="Test cases assigned to this plan"
                    class="min-w-full divide-y divide-slate-200"
                >
                    <thead class="bg-slate-50/80">
                        <tr
                            class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            <th class="px-4 py-4">Case</th>
                            <th class="px-4 py-4">Module</th>
                            <th class="px-4 py-4 text-center">Priority</th>
                            <th class="px-4 py-4">Automation</th>
                            <th class="px-4 py-4">Behavior / Type</th>
                            <th class="w-16 px-4 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        {#if !testPlan.test_cases || testPlan.test_cases.length === 0}
                            <tr>
                                <td
                                    colspan="6"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    No test cases have been assigned to this
                                    plan yet.
                                </td>
                            </tr>
                        {:else}
                            {#each testPlan.test_cases as testCase (testCase.id)}
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-5">
                                        <div class="space-y-1">
                                            <p
                                                class="font-semibold text-[#002b57]"
                                            >
                                                {testCase.title}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {testCase.case_key}
                                            </p>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-5 text-sm text-slate-600"
                                    >
                                        {testCase.module.name ??
                                            'Uncategorized'}
                                    </td>
                                    <td class="px-4 py-5 text-center">
                                        <span
                                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                        >
                                            {testCase.priority}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-5 text-sm text-slate-600"
                                    >
                                        {testCase.automation_status}
                                    </td>
                                    <td
                                        class="px-4 py-5 text-sm text-slate-600"
                                    >
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="font-medium text-[#071a2f]"
                                            >
                                                {testCase.test_behavior}
                                            </span>
                                            <span>{testCase.test_type}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-5 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-9 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            disabled={removingCaseId ===
                                                testCase.id}
                                            onclick={() =>
                                                removeAssignedCase(testCase.id)}
                                        >
                                            {#if removingCaseId === testCase.id}
                                                <Spinner />
                                            {:else}
                                                <Trash2 class="size-4" />
                                            {/if}
                                        </Button>
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-4 lg:px-6"
            >
                <div>
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Test Runs
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Recent execution runs created from this plan.
                    </p>
                </div>
                <span
                    class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700"
                >
                    {testRuns.meta.total}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table
                    id="test-plan-runs-table"
                    aria-label="Test runs for this plan"
                    class="min-w-full divide-y divide-slate-200"
                >
                    <thead class="bg-slate-50/80">
                        <tr
                            class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            <th class="px-4 py-4">Run Name</th>
                            <th class="px-4 py-4 text-center">Status</th>
                            <th class="px-4 py-4 text-center">Environment</th>
                            <th class="px-4 py-4 text-center">Priority</th>
                            <th class="px-4 py-4">Tester</th>
                            <th class="px-4 py-4 text-center">Cases</th>
                            <th class="px-4 py-4">Updated</th>
                            <th class="w-24 px-4 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        {#if testRuns.data.length === 0}
                            <tr>
                                <td
                                    colspan="8"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    No test runs have been created for this plan
                                    yet.
                                </td>
                            </tr>
                        {:else}
                            {#each testRuns.data as testRun (testRun.id)}
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-5">
                                        <div class="space-y-1">
                                            <Link
                                                href={testExecutionShow(
                                                    testRun.id,
                                                )}
                                                class="font-semibold text-[#002b57] transition-colors hover:text-[#003c78] hover:underline"
                                            >
                                                {testRun.name}
                                            </Link>
                                            <p class="text-xs text-slate-500">
                                                Created {formatDateTime(
                                                    testRun.created_at,
                                                )}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-5 text-center">
                                        <span
                                            class={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusClass(testRun.status)}`}
                                        >
                                            {testRun.status}
                                        </span>
                                    </td>
                                    <td class="px-4 py-5 text-center">
                                        <span
                                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                        >
                                            {testRun.environment}
                                        </span>
                                    </td>
                                    <td class="px-4 py-5 text-center">
                                        <span
                                            class={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${priorityClass(testRun.priority)}`}
                                        >
                                            {testRun.priority}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-5 text-sm text-slate-600"
                                    >
                                        {testRun.tester_name}
                                    </td>
                                    <td
                                        class="px-4 py-5 text-center text-sm text-slate-600"
                                    >
                                        {testRun.assigned_test_cases_count ?? 0}
                                    </td>
                                    <td
                                        class="px-4 py-5 text-sm text-slate-600"
                                    >
                                        {formatDateTime(testRun.updated_at)}
                                    </td>
                                    <td class="px-4 py-5 text-right">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            class="border-slate-200 bg-white text-[#071a2f] hover:bg-slate-50"
                                        >
                                            {#snippet children(props)}
                                                <Link
                                                    {...props}
                                                    href={testExecutionShow(
                                                        testRun.id,
                                                    )}
                                                    class={props.class}
                                                >
                                                    <Eye class="size-4" />
                                                    View
                                                </Link>
                                            {/snippet}
                                        </Button>
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

<Dialog bind:open={addCasesOpen}>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-6xl"
    >
        <DialogTitle>Add Test Cases</DialogTitle>
        <DialogDescription>
            Search and bulk-assign repository cases from the same project.
        </DialogDescription>

        <CaseSelector
            payload={availablePayload}
            filters={availableFilters}
            loading={availableLoading}
            adding={assigning}
            {selectedCaseIds}
            onsearch={() =>
                reloadAvailable({ page: 1, search: availableFilters.search })}
            onfilterchange={(nextFilters) =>
                reloadAvailable({
                    ...nextFilters,
                    page: 1,
                })}
            onpagechange={(page) => reloadAvailable({ page })}
            ontogglecase={toggleSelectedCase}
            ontogglevisible={toggleVisibleCases}
            onclearselection={() => (selectedCaseIds = [])}
            onaddselected={addSelectedCases}
        />
    </DialogContent>
</Dialog>
