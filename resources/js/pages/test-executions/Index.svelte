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
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import Eye from 'lucide-svelte/icons/eye';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Play from 'lucide-svelte/icons/play';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import TestRunForm from '@/components/test-executions/TestRunForm.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import {
        createTestRun,
        deleteTestRun,
        fetchTestRuns,
        startTestRun,
        TestRunApiError,
        updateTestRun,
    } from '@/lib/api/testRuns';
    import type {
        TestRunFilters,
        TestRunFormValues,
        TestRunItem,
        TestRunListResponse,
        TestRunPriority,
        TestRunStatus,
    } from '@/lib/api/testRuns';
    import { show as testExecutionsShow } from '@/routes/test-executions';

    let {
        initialPayload,
    }: {
        initialPayload: TestRunListResponse;
    } = $props();

    const clonePayload = (
        response: TestRunListResponse,
    ): TestRunListResponse => ({
        ...response,
        data: response.data.map((testRun) => ({
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
            ...response.meta,
            links: response.meta.links.map((link) => ({ ...link })),
        },
        stats: { ...response.stats },
        filters: { ...response.filters },
        options: {
            testPlans: response.options.testPlans.map((testPlan) => ({
                ...testPlan,
                project: { ...testPlan.project },
                test_cases: testPlan.test_cases.map((testCase) => ({
                    ...testCase,
                    module: { ...testCase.module },
                })),
            })),
            environments: [...response.options.environments],
            priorities: [...response.options.priorities],
            statuses: [...response.options.statuses],
            caseStatuses: [...(response.options.caseStatuses ?? [])],
        },
    });

    const getStartingPayload = (): TestRunListResponse =>
        clonePayload(initialPayload);

    const startingPayload = getStartingPayload();

    let payload = $state(startingPayload);
    let filters = $state<TestRunFilters>({ ...startingPayload.filters });
    let loading = $state(false);
    let createOpen = $state(false);
    let editingRun = $state<TestRunItem | null>(null);
    let deletingRun = $state<TestRunItem | null>(null);
    let createErrors = $state<Record<string, string>>({});
    let editErrors = $state<Record<string, string>>({});
    let createProcessing = $state(false);
    let editProcessing = $state(false);
    let deleteProcessing = $state(false);
    let startingRunId = $state<number | null>(null);

    const createDefaultValues = (
        response: TestRunListResponse,
    ): TestRunFormValues => ({
        test_plan_id: response.options.testPlans[0]?.id ?? null,
        test_case_ids: [],
        name: '',
        environment: response.options.environments[0] ?? 'Development',
        priority:
            response.options.priorities.find(
                (priority) => priority === 'Medium',
            ) ??
            response.options.priorities[0] ??
            'Medium',
        description: '',
        tester_name: '',
    });

    let createValues = $state<TestRunFormValues>(
        createDefaultValues(startingPayload),
    );
    let editValues = $state<TestRunFormValues>(
        createDefaultValues(startingPayload),
    );

    const statCards = $derived([
        {
            title: 'Total Runs',
            value: payload.stats.totalRuns,
            icon: ClipboardList,
            iconClass: 'bg-sky-50 text-sky-700',
        },
        {
            title: 'New',
            value: payload.stats.newRuns,
            icon: Clock3,
            iconClass: 'bg-slate-100 text-slate-700',
        },
        {
            title: 'Active',
            value: payload.stats.activeRuns,
            icon: Play,
            iconClass: 'bg-amber-50 text-amber-700',
        },
        {
            title: 'Completed',
            value: payload.stats.doneRuns,
            icon: CircleCheck,
            iconClass: 'bg-emerald-50 text-emerald-700',
        },
    ]);

    const pageNumbers = $derived.by(() =>
        Array.from({ length: payload.meta.last_page }, (_, index) => index + 1),
    );

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

    const environmentClass = (
        environment: TestRunItem['environment'],
    ): string => {
        const classes: Record<TestRunItem['environment'], string> = {
            Development: 'border-sky-200 bg-sky-50 text-sky-700',
            Staging: 'border-violet-200 bg-violet-50 text-violet-700',
            UAT: 'border-amber-200 bg-amber-50 text-amber-700',
            Production: 'border-rose-200 bg-rose-50 text-rose-700',
        };

        return classes[environment];
    };

    const progressValue = (testRun: TestRunItem): number =>
        testRun.execution_summary?.progress ?? 0;

    const resetCreateForm = (): void => {
        createValues = createDefaultValues(payload);
        createErrors = {};
    };

    const hydrateEditForm = (testRun: TestRunItem): void => {
        editValues = {
            test_plan_id: testRun.test_plan.id,
            test_case_ids:
                testRun.test_cases?.map((testCase) => testCase.id) ?? [],
            name: testRun.name,
            environment: testRun.environment,
            priority: testRun.priority,
            description: testRun.description ?? '',
            tester_name: testRun.tester_name,
        };
        editErrors = {};
    };

    const syncFromResponse = (response: TestRunListResponse): void => {
        payload = clonePayload(response);
        filters = { ...response.filters };
    };

    const reload = async (
        nextFilters: Partial<TestRunFilters> = {},
    ): Promise<void> => {
        loading = true;

        try {
            const response = await fetchTestRuns({
                ...filters,
                ...nextFilters,
            });

            syncFromResponse(response);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load the test runs.',
            );
        } finally {
            loading = false;
        }
    };

    const submitCreate = async (): Promise<void> => {
        createProcessing = true;
        createErrors = {};

        try {
            await createTestRun(createValues);
            toast.success('Test run created.');
            createOpen = false;
            resetCreateForm();
            await reload({ page: 1 });
        } catch (error) {
            if (error instanceof TestRunApiError && error.status === 422) {
                createErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to create the test run.',
                );
            }
        } finally {
            createProcessing = false;
        }
    };

    const submitUpdate = async (): Promise<void> => {
        if (!editingRun) {
            return;
        }

        editProcessing = true;
        editErrors = {};

        try {
            await updateTestRun(editingRun.id, editValues);
            toast.success('Test run updated.');
            editingRun = null;
            await reload({ page: filters.page });
        } catch (error) {
            if (error instanceof TestRunApiError && error.status === 422) {
                editErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to update the test run.',
                );
            }
        } finally {
            editProcessing = false;
        }
    };

    const submitDelete = async (): Promise<void> => {
        if (!deletingRun) {
            return;
        }

        deleteProcessing = true;

        try {
            await deleteTestRun(deletingRun.id);
            toast.success('Test run deleted.');
            deletingRun = null;
            await reload({ page: filters.page });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to delete the test run.',
            );
        } finally {
            deleteProcessing = false;
        }
    };

    const startRun = async (testRun: TestRunItem): Promise<void> => {
        if (startingRunId !== null || testRun.status !== 'New') {
            return;
        }

        startingRunId = testRun.id;

        try {
            await startTestRun(testRun.id);
            toast.success('Test run started.');
            await reload({ page: filters.page });
        } catch (error) {
            if (error instanceof TestRunApiError && error.status === 422) {
                toast.error(
                    error.errors.status ?? 'Unable to start the test run.',
                );
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to start the test run.',
                );
            }
        } finally {
            startingRunId = null;
        }
    };

    const openCreateDialog = (): void => {
        resetCreateForm();
        createOpen = true;
    };

    const openEditDialog = (testRun: TestRunItem): void => {
        editingRun = testRun;
        hydrateEditForm(testRun);
    };

    const onFilterChange = async (
        nextFilters: Partial<TestRunFilters>,
    ): Promise<void> => {
        filters = {
            ...filters,
            ...nextFilters,
        };
        await reload({ ...nextFilters, page: 1 });
    };

    const onPerPageChange = async (perPage: number): Promise<void> => {
        filters.per_page = perPage;
        await reload({ page: 1, per_page: perPage });
    };

    const goToPage = async (page: number): Promise<void> => {
        if (page < 1 || page > payload.meta.last_page || loading) {
            return;
        }

        filters.page = page;
        await reload({ page });
    };
</script>

<AppHead title="Test Executions" />

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-[#071a2f]">
                    Recent Test Runs
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Monitor and manage the status of your live and completed
                    test suites.
                </p>
            </div>

            <Button
                class="bg-[#002b57] px-5 text-white shadow-sm hover:bg-[#003c78]"
                onclick={openCreateDialog}
            >
                <Plus class="size-4" />
                Create Test Run
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {#each statCards as stat (stat.title)}
                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500"
                            >
                                {stat.title}
                            </p>
                            <p class="mt-3 text-3xl font-bold text-[#071a2f]">
                                {stat.value}
                            </p>
                        </div>
                        <span
                            class={`flex size-10 items-center justify-center rounded-xl ${stat.iconClass}`}
                        >
                            <stat.icon class="size-5" />
                        </span>
                    </div>
                </article>
            {/each}
        </div>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 xl:flex-row xl:items-center xl:justify-between lg:px-6"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative min-w-[15rem]">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            id="test-runs-search"
                            aria-label="Search test runs"
                            bind:value={filters.search}
                            class="border-slate-300 bg-white pl-9 text-[#071a2f] placeholder:text-slate-400"
                            placeholder="Search runs, cases, testers"
                            onkeydown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    void reload({
                                        page: 1,
                                        search: filters.search,
                                    });
                                }
                            }}
                        />
                    </div>

                    <select
                        id="test-runs-environment-filter"
                        aria-label="Filter test runs by environment"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.environment}
                        onchange={(event) =>
                            onFilterChange({
                                environment: (
                                    event.currentTarget as HTMLSelectElement
                                ).value,
                            })}
                    >
                        <option value="all">All Environments</option>
                        {#each payload.options.environments as environment (environment)}
                            <option value={environment}>{environment}</option>
                        {/each}
                    </select>

                    <select
                        id="test-runs-priority-filter"
                        aria-label="Filter test runs by priority"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.priority}
                        onchange={(event) =>
                            onFilterChange({
                                priority: (
                                    event.currentTarget as HTMLSelectElement
                                ).value,
                            })}
                    >
                        <option value="all">All Priorities</option>
                        {#each payload.options.priorities as priority (priority)}
                            <option value={priority}>{priority}</option>
                        {/each}
                    </select>

                    <select
                        id="test-runs-status-filter"
                        aria-label="Filter test runs by status"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.status}
                        onchange={(event) =>
                            onFilterChange({
                                status: (
                                    event.currentTarget as HTMLSelectElement
                                ).value,
                            })}
                    >
                        <option value="all">All Statuses</option>
                        {#each payload.options.statuses as status (status)}
                            <option value={status}>{status}</option>
                        {/each}
                    </select>

                    <Button
                        variant="outline"
                        class="border-slate-200 bg-white"
                        onclick={() =>
                            reload({
                                page: 1,
                                search: filters.search,
                            })}
                    >
                        Search
                    </Button>
                </div>

                <p class="text-sm text-slate-600">
                    Showing {payload.meta.from ?? 0}-{payload.meta.to ?? 0} of {payload
                        .meta.total} Test Runs
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    id="test-runs-table"
                    aria-label="Test execution runs"
                    class="min-w-full divide-y divide-slate-200"
                >
                    <thead class="bg-slate-50/80">
                        <tr
                            class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            <th class="px-4 py-4 lg:px-6">Run Name</th>
                            <th class="px-4 py-4 text-center">Environment</th>
                            <th class="px-4 py-4 text-center">Priority</th>
                            <th class="px-4 py-4 text-center">Status</th>
                            <th class="px-4 py-4">Progress</th>
                            <th class="px-4 py-4 text-right lg:px-6">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        {#if loading}
                            <tr>
                                <td
                                    colspan="6"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    <div
                                        class="flex items-center justify-center gap-3"
                                    >
                                        <Spinner />
                                        Loading test runs...
                                    </div>
                                </td>
                            </tr>
                        {:else if payload.data.length === 0}
                            <tr>
                                <td
                                    colspan="6"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    No test runs matched the current filters.
                                </td>
                            </tr>
                        {:else}
                            {#each payload.data as testRun (testRun.id)}
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-4 lg:px-6">
                                        <div class="space-y-1">
                                            <p
                                                class="font-semibold text-[#002b57]"
                                            >
                                                {testRun.name}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${environmentClass(
                                                testRun.environment,
                                            )}`}
                                        >
                                            {testRun.environment}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${priorityClass(
                                                testRun.priority,
                                            )}`}
                                        >
                                            {testRun.priority}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class={`inline-flex h-9 min-w-24 items-center justify-center rounded-full border px-3 text-sm font-semibold ${statusClass(
                                                testRun.status,
                                            )}`}
                                        >
                                            {testRun.status}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="min-w-32">
                                            <div
                                                class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-600"
                                            >
                                                <span
                                                    >{progressValue(
                                                        testRun,
                                                    )}%</span
                                                >
                                                <span class="text-slate-400">
                                                    {testRun.execution_summary
                                                        ?.completed ??
                                                        0}/{testRun
                                                        .execution_summary
                                                        ?.total ?? 0}
                                                </span>
                                            </div>
                                            <div
                                                class="mt-2 h-2 rounded-full bg-slate-100"
                                            >
                                                <div
                                                    class="h-2 rounded-full bg-emerald-500"
                                                    style={`width: ${progressValue(testRun)}%`}
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right lg:px-6">
                                        <div
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="border-amber-200 bg-white text-amber-700 hover:bg-amber-50 hover:text-amber-800 disabled:opacity-60"
                                                disabled={testRun.status !==
                                                    'New' ||
                                                    startingRunId ===
                                                        testRun.id}
                                                onclick={() =>
                                                    startRun(testRun)}
                                            >
                                                {#if startingRunId === testRun.id}
                                                    <Spinner />
                                                {:else}
                                                    <Play class="size-4" />
                                                {/if}
                                                Start
                                            </Button>
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                class="border-slate-200 bg-white text-[#071a2f] hover:bg-slate-50"
                                            >
                                                {#snippet children(props)}
                                                    <Link
                                                        {...props}
                                                        href={testExecutionsShow(
                                                            testRun.id,
                                                        )}
                                                        class={props.class}
                                                    >
                                                        <Eye class="size-4" />
                                                        View
                                                    </Link>
                                                {/snippet}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="border-slate-200 bg-white text-[#071a2f] hover:bg-slate-50"
                                                onclick={() =>
                                                    openEditDialog(testRun)}
                                            >
                                                <Pencil class="size-4" />
                                                Edit
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="border-red-200 bg-white text-red-600 hover:bg-red-50 hover:text-red-700"
                                                onclick={() =>
                                                    (deletingRun = testRun)}
                                            >
                                                <Trash2 class="size-4" />
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-slate-200 px-4 py-4 md:flex-row md:items-center md:justify-between lg:px-6"
            >
                <div class="flex items-center gap-3 text-sm text-slate-600">
                    <span>Rows per page:</span>
                    <select
                        id="test-runs-rows-per-page"
                        aria-label="Test runs rows per page"
                        class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.per_page}
                        onchange={(event) =>
                            onPerPageChange(
                                Number(
                                    (event.currentTarget as HTMLSelectElement)
                                        .value,
                                ),
                            )}
                    >
                        <option value={10}>10</option>
                        <option value={20}>20</option>
                        <option value={50}>50</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        class="border-slate-200 bg-white"
                        disabled={filters.page <= 1 || loading}
                        onclick={() => goToPage(filters.page - 1)}
                    >
                        Prev
                    </Button>
                    {#each pageNumbers as pageNumber (pageNumber)}
                        <Button
                            variant={pageNumber === filters.page
                                ? 'default'
                                : 'outline'}
                            class={pageNumber === filters.page
                                ? 'bg-[#002b57] text-white hover:bg-[#003c78]'
                                : 'border-slate-200 bg-white'}
                            disabled={loading}
                            onclick={() => goToPage(pageNumber)}
                        >
                            {pageNumber}
                        </Button>
                    {/each}
                    <Button
                        variant="outline"
                        class="border-slate-200 bg-white"
                        disabled={filters.page >= payload.meta.last_page ||
                            loading}
                        onclick={() => goToPage(filters.page + 1)}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </section>
    </div>
</div>

<Dialog bind:open={createOpen}>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-5xl"
    >
        <DialogTitle>Create Test Run</DialogTitle>
        <DialogDescription>
            Select a plan, choose its assigned test cases, and prepare the run
            for execution tracking.
        </DialogDescription>
        <TestRunForm
            values={createValues}
            errors={createErrors}
            processing={createProcessing}
            testPlans={payload.options.testPlans}
            environments={payload.options.environments}
            priorities={payload.options.priorities}
            submitLabel="Create Run"
            onsubmit={submitCreate}
            oncancel={() => {
                createOpen = false;
                resetCreateForm();
            }}
        />
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(editingRun)}
    onOpenChange={(open) => {
        if (!open) {
            editingRun = null;
            editErrors = {};
        }
    }}
>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-5xl"
    >
        <DialogTitle>Edit Test Run</DialogTitle>
        <DialogDescription>
            Update the run details and selected test cases.
        </DialogDescription>
        {#if editingRun}
            <TestRunForm
                values={editValues}
                errors={editErrors}
                processing={editProcessing}
                testPlans={payload.options.testPlans}
                environments={payload.options.environments}
                priorities={payload.options.priorities}
                submitLabel="Save Changes"
                onsubmit={submitUpdate}
                oncancel={() => {
                    editingRun = null;
                    editErrors = {};
                }}
            />
        {/if}
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(deletingRun)}
    onOpenChange={(open) => {
        if (!open) {
            deletingRun = null;
        }
    }}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Test Run</DialogTitle>
        <DialogDescription>
            This will permanently remove the test run and its selected cases.
        </DialogDescription>

        {#if deletingRun}
            <div
                class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <p class="font-semibold text-[#071a2f]">{deletingRun.name}</p>
                <p class="mt-1 text-sm text-slate-600">
                    {deletingRun.test_plan.name} - {deletingRun.tester_name}
                </p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (deletingRun = null)}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    disabled={deleteProcessing}
                    onclick={submitDelete}
                >
                    {#if deleteProcessing}<Spinner />{/if}
                    Delete Test Run
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>
