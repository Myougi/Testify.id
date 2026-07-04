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
    import Eye from 'lucide-svelte/icons/eye';
    import FileCheck2 from 'lucide-svelte/icons/file-check-2';
    import Flame from 'lucide-svelte/icons/flame';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import AiDraftDialog from '@/components/ai/AiDraftDialog.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PlanStatusBadge from '@/components/test-plans/PlanStatusBadge.svelte';
    import TestPlanForm from '@/components/test-plans/TestPlanForm.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogTrigger,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import {
        AiDraftApiError,
        generateAiDraft,
        summarizeAiSourceContext,
    } from '@/lib/api/ai';
    import type { TestPlanAiDraft } from '@/lib/api/ai';
    import {
        createTestPlan,
        deleteTestPlan,
        fetchTestPlans,
        TestPlanApiError,
        updateTestPlan,
    } from '@/lib/api/testPlans';
    import type {
        TestPlanFilters,
        TestPlanFormValues,
        TestPlanItem,
        TestPlanListResponse,
    } from '@/lib/api/testPlans';
    import { show as testPlansShowRoute } from '@/routes/test-plans';

    let {
        initialPayload,
    }: {
        initialPayload: TestPlanListResponse;
    } = $props();

    const getStartingPayload = (): TestPlanListResponse => ({
        ...initialPayload,
        data: initialPayload.data.map((testPlan) => ({
            ...testPlan,
            project: {
                ...testPlan.project,
            },
        })),
        meta: {
            ...initialPayload.meta,
            links: initialPayload.meta.links.map((link) => ({ ...link })),
        },
        stats: {
            ...initialPayload.stats,
        },
        filters: {
            ...initialPayload.filters,
        },
        options: {
            projects: initialPayload.options.projects.map((project) => ({
                ...project,
            })),
            statuses: [...initialPayload.options.statuses],
            priorities: [...initialPayload.options.priorities],
            selectedProjectId: initialPayload.options.selectedProjectId,
        },
    });
    const startingPayload = getStartingPayload();

    let payload = $state(startingPayload);
    let filters = $state<TestPlanFilters>({
        ...startingPayload.filters,
    });
    let loading = $state(false);
    let createOpen = $state(false);
    let editingPlan = $state<TestPlanItem | null>(null);
    let deletingPlan = $state<TestPlanItem | null>(null);
    let createErrors = $state<Record<string, string>>({});
    let editErrors = $state<Record<string, string>>({});
    let createProcessing = $state(false);
    let editProcessing = $state(false);
    let deleteProcessing = $state(false);

    const createDefaultValues = (
        response: TestPlanListResponse,
    ): TestPlanFormValues => ({
        project_id:
            response.options.selectedProjectId ??
            response.options.projects[0]?.id ??
            null,
        name: '',
        description: '',
        status: response.options.statuses[0] ?? 'Draft',
        priority:
            response.options.priorities.find(
                (priority) => priority === 'Medium',
            ) ??
            response.options.priorities[0] ??
            'Medium',
        start_date: '',
        due_date: '',
        release_version: '',
        tester: '',
        scope: '',
        strategy: '',
        criteria: '',
    });

    let createValues = $state<TestPlanFormValues>(
        createDefaultValues(startingPayload),
    );
    let editValues = $state<TestPlanFormValues>(
        createDefaultValues(startingPayload),
    );
    let createAiOpen = $state(false);
    let createAiPrompt = $state('');
    let createAiProcessing = $state(false);
    let createAiError = $state<string | null>(null);
    let createAiWarnings = $state<string[]>([]);
    let createAiContextItems = $state<string[]>([]);
    let createAiDraft = $state<TestPlanAiDraft | null>(null);

    const statCards = $derived([
        {
            title: 'Total Plans',
            value: payload.stats.totalPlans,
            icon: FileCheck2,
            iconClass: 'bg-sky-50 text-sky-700',
        },
        {
            title: 'Active',
            value: payload.stats.activePlans,
            icon: Flame,
            iconClass: 'bg-amber-50 text-amber-700',
        },
        {
            title: 'Completed',
            value: payload.stats.completedPlans,
            icon: FileCheck2,
            iconClass: 'bg-emerald-50 text-emerald-700',
        },
        {
            title: 'Overdue',
            value: payload.stats.overduePlans,
            icon: ShieldAlert,
            iconClass: 'bg-rose-50 text-rose-700',
        },
    ]);

    const pageNumbers = $derived.by(() => {
        const totalPages = payload.meta.last_page;

        return Array.from({ length: totalPages }, (_, index) => index + 1);
    });

    const canCreatePlan = $derived(payload.options.projects.length > 0);
    const isProjectLocked = $derived(
        payload.options.selectedProjectId !== null,
    );

    const resetCreateForm = (): void => {
        createValues = createDefaultValues(payload);
        createErrors = {};
    };

    const resetCreateAi = (): void => {
        createAiPrompt = '';
        createAiProcessing = false;
        createAiError = null;
        createAiWarnings = [];
        createAiContextItems = [];
        createAiDraft = null;
    };

    const hydrateEditForm = (testPlan: TestPlanItem): void => {
        editValues = {
            project_id: testPlan.project.id,
            name: testPlan.name,
            description: testPlan.description ?? '',
            status: testPlan.status,
            priority: testPlan.priority,
            start_date: testPlan.start_date ?? '',
            due_date: testPlan.due_date ?? '',
            release_version: testPlan.release_version ?? '',
            tester: testPlan.tester ?? '',
            scope: testPlan.scope ?? '',
            strategy: testPlan.strategy ?? '',
            criteria: testPlan.criteria ?? '',
        };
        editErrors = {};
    };

    const syncFromResponse = (response: TestPlanListResponse): void => {
        payload = {
            ...response,
            options: {
                ...response.options,
                selectedProjectId: payload.options.selectedProjectId,
            },
        };
        filters = {
            ...response.filters,
        };
    };

    const reload = async (
        nextFilters: Partial<TestPlanFilters> = {},
    ): Promise<void> => {
        loading = true;

        try {
            const response = await fetchTestPlans({
                ...filters,
                ...nextFilters,
            });

            syncFromResponse(response);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load the test plans.',
            );
        } finally {
            loading = false;
        }
    };

    const submitCreate = async (): Promise<void> => {
        createProcessing = true;
        createErrors = {};

        try {
            await createTestPlan(createValues);
            toast.success('Test plan created.');
            createOpen = false;
            resetCreateForm();
            await reload({ page: 1 });
        } catch (error) {
            if (error instanceof TestPlanApiError && error.status === 422) {
                createErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to create the test plan.',
                );
            }
        } finally {
            createProcessing = false;
        }
    };

    const generateCreatePlanDraft = async (): Promise<void> => {
        if (createValues.project_id === null) {
            createAiError =
                'Select a project before generating a test plan draft.';

            return;
        }

        createAiProcessing = true;
        createAiError = null;

        try {
            const response = await generateAiDraft<TestPlanAiDraft>({
                artifact_type: 'test_plan',
                prompt: createAiPrompt,
                project_id: createValues.project_id,
            });

            createAiDraft = response.draft;
            createAiWarnings = response.warnings;
            createAiContextItems = summarizeAiSourceContext(
                response.source_context,
            );
        } catch (error) {
            if (error instanceof AiDraftApiError && error.status === 422) {
                createAiError = Object.values(error.errors)[0] ?? error.message;
            } else {
                createAiError =
                    error instanceof Error
                        ? error.message
                        : 'Unable to generate the test plan draft.';
            }
        } finally {
            createAiProcessing = false;
        }
    };

    const applyCreatePlanDraft = (): void => {
        if (!createAiDraft) {
            return;
        }

        createValues = {
            ...createValues,
            name: createAiDraft.name,
            description: createAiDraft.description ?? '',
            scope: createAiDraft.scope ?? '',
            strategy: createAiDraft.strategy ?? '',
            criteria: createAiDraft.criteria ?? '',
            status: createAiDraft.status,
            priority: createAiDraft.priority,
            start_date: createAiDraft.start_date ?? '',
            due_date: createAiDraft.due_date ?? '',
            release_version: createAiDraft.release_version ?? '',
            tester: createAiDraft.tester ?? '',
        };
        createAiOpen = false;
    };

    const submitUpdate = async (): Promise<void> => {
        if (!editingPlan) {
            return;
        }

        editProcessing = true;
        editErrors = {};

        try {
            await updateTestPlan(editingPlan.id, editValues);
            toast.success('Test plan updated.');
            editingPlan = null;
            await reload({ page: filters.page });
        } catch (error) {
            if (error instanceof TestPlanApiError && error.status === 422) {
                editErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to update the test plan.',
                );
            }
        } finally {
            editProcessing = false;
        }
    };

    const submitDelete = async (): Promise<void> => {
        if (!deletingPlan) {
            return;
        }

        deleteProcessing = true;

        try {
            await deleteTestPlan(deletingPlan.id);
            toast.success('Test plan deleted.');
            deletingPlan = null;
            await reload({ page: filters.page });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to delete the test plan.',
            );
        } finally {
            deleteProcessing = false;
        }
    };

    const openEditDialog = (testPlan: TestPlanItem): void => {
        editingPlan = testPlan;
        hydrateEditForm(testPlan);
    };

    const onStatusChange = async (status: string): Promise<void> => {
        filters.status = status;
        await reload({ page: 1, status });
    };

    const onPriorityChange = async (priority: string): Promise<void> => {
        filters.priority = priority;
        await reload({ page: 1, priority });
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

<AppHead title="Test Plans" />

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-[#071a2f]">
                    Test Plans
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Manage and monitor the verification strategies for your
                    active software releases.
                </p>
            </div>

            <Dialog
                bind:open={createOpen}
                onOpenChange={(open) => {
                    if (open) {
                        resetCreateForm();
                        resetCreateAi();
                    }
                }}
            >
                <DialogTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            onclick={() => {
                                if (typeof props.onClick === 'function') {
                                    props.onClick();
                                }
                            }}
                            aria-expanded={Boolean(props['aria-expanded'])}
                            class="bg-[#002b57] px-5 text-white shadow-sm hover:bg-[#003c78]"
                            disabled={!canCreatePlan}
                        >
                            <Plus class="size-4" />
                            New Test Plan
                        </Button>
                    {/snippet}
                </DialogTrigger>
                <DialogContent
                    class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-5xl"
                >
                    <DialogTitle>Create New Test Plan</DialogTitle>
                    <DialogDescription>
                        Define the scope, schedule, and test suites for your
                        next quality assurance cycle. Ensure all parameters
                        align with the project roadmap.
                    </DialogDescription>
                    <div class="mt-4 flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-slate-200 bg-white text-[#071a2f] hover:bg-slate-50"
                            onclick={() => {
                                createAiOpen = true;
                            }}
                        >
                            <Sparkles class="size-4" />
                            Generate Draft
                        </Button>
                    </div>
                    <TestPlanForm
                        values={createValues}
                        errors={createErrors}
                        processing={createProcessing}
                        projects={payload.options.projects}
                        projectDisabled={isProjectLocked}
                        statuses={payload.options.statuses}
                        priorities={payload.options.priorities}
                        submitLabel="Create Plan"
                        onsubmit={submitCreate}
                        oncancel={() => {
                            createOpen = false;
                            resetCreateForm();
                        }}
                    />
                </DialogContent>
            </Dialog>
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
                class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-6"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative min-w-[15rem]">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            id="test-plans-search"
                            aria-label="Search test plans"
                            bind:value={filters.search}
                            class="border-slate-300 bg-white pl-9 text-[#071a2f] placeholder:text-slate-400"
                            placeholder="Search plans, releases, or testers"
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
                        id="test-plans-status-filter"
                        aria-label="Filter test plans by status"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.status}
                        onchange={(event) =>
                            onStatusChange(
                                (event.currentTarget as HTMLSelectElement)
                                    .value,
                            )}
                    >
                        <option value="all">All Statuses</option>
                        {#each payload.options.statuses as status (status)}
                            <option value={status}>{status}</option>
                        {/each}
                    </select>

                    <select
                        id="test-plans-priority-filter"
                        aria-label="Filter test plans by priority"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.priority}
                        onchange={(event) =>
                            onPriorityChange(
                                (event.currentTarget as HTMLSelectElement)
                                    .value,
                            )}
                    >
                        <option value="all">All Priorities</option>
                        {#each payload.options.priorities as priority (priority)}
                            <option value={priority}>{priority}</option>
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
                        .meta.total} Test Plans
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    id="test-plans-table"
                    aria-label="Test plans"
                    class="min-w-full divide-y divide-slate-200"
                >
                    <thead class="bg-slate-50/80">
                        <tr
                            class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            <th class="px-4 py-4 lg:px-6">Plan Name</th>
                            <th class="px-4 py-4 text-center">Status</th>
                            <th class="px-4 py-4 text-center">Priority</th>
                            <th class="px-4 py-4 text-center">Start Date</th>
                            <th class="px-4 py-4 text-center">End Date</th>
                            <th class="px-4 py-4 text-center"
                                >Assigned Tester</th
                            >
                            <th class="px-4 py-4 text-center lg:px-6"
                                >Actions</th
                            >
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        {#if loading}
                            <tr>
                                <td
                                    colspan="7"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    <div
                                        class="flex items-center justify-center gap-3"
                                    >
                                        <Spinner />
                                        Loading test plans...
                                    </div>
                                </td>
                            </tr>
                        {:else if payload.data.length === 0}
                            <tr>
                                <td
                                    colspan="7"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    No test plans matched the current filters.
                                </td>
                            </tr>
                        {:else}
                            {#each payload.data as testPlan (testPlan.id)}
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-4 lg:px-6">
                                        <div class="space-y-1">
                                            <p
                                                class="font-semibold text-[#002b57]"
                                            >
                                                {testPlan.name}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <PlanStatusBadge
                                            status={testPlan.status}
                                        />
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                        >
                                            {testPlan.priority}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-4 text-center text-sm text-slate-600"
                                    >
                                        {testPlan.start_date ?? 'Not set'}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-center text-sm text-slate-600"
                                    >
                                        {testPlan.due_date ?? 'Not set'}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-center text-sm text-[#071a2f]"
                                    >
                                        {testPlan.tester ?? 'Unassigned'}
                                    </td>
                                    <td class="px-4 py-4 text-right lg:px-6">
                                        <div
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                class="border-slate-200 bg-white text-[#071a2f] hover:bg-slate-50"
                                            >
                                                {#snippet children(props)}
                                                    <Link
                                                        {...props}
                                                        href={testPlansShowRoute(
                                                            testPlan.id,
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
                                                    openEditDialog(testPlan)}
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
                                                    (deletingPlan = testPlan)}
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
                        id="test-plans-rows-per-page"
                        aria-label="Test plans rows per page"
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

<Dialog
    open={Boolean(editingPlan)}
    onOpenChange={(open) => {
        if (!open) {
            editingPlan = null;
            editErrors = {};
        }
    }}
>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-5xl"
    >
        <DialogTitle>Edit Test Plan</DialogTitle>
        <DialogDescription>
            Update the scope, schedule, ownership, and methodology for this test
            plan.
        </DialogDescription>
        {#if editingPlan}
            <TestPlanForm
                values={editValues}
                errors={editErrors}
                processing={editProcessing}
                projects={payload.options.projects}
                projectDisabled={false}
                statuses={payload.options.statuses}
                priorities={payload.options.priorities}
                submitLabel="Save Changes"
                onsubmit={submitUpdate}
                oncancel={() => {
                    editingPlan = null;
                    editErrors = {};
                }}
            />
        {/if}
    </DialogContent>
</Dialog>

{#snippet createAiPreview()}
    {#if createAiDraft}
        <div class="grid gap-4 text-sm text-[#071a2f]">
            <div class="space-y-1">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                >
                    Draft Name
                </p>
                <p class="text-base font-semibold">{createAiDraft.name}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                    >
                        Priority
                    </p>
                    <p>{createAiDraft.priority}</p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                    >
                        Tester
                    </p>
                    <p>{createAiDraft.tester ?? 'Not set'}</p>
                </div>
            </div>
            <div class="grid gap-3">
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                    >
                        Description
                    </p>
                    <p>{createAiDraft.description ?? 'Not provided'}</p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                    >
                        Scope
                    </p>
                    <p>{createAiDraft.scope ?? 'Not provided'}</p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                    >
                        Strategy
                    </p>
                    <p>{createAiDraft.strategy ?? 'Not provided'}</p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
                    >
                        Criteria
                    </p>
                    <p>{createAiDraft.criteria ?? 'Not provided'}</p>
                </div>
            </div>
            <div class="flex justify-end">
                <Button
                    type="button"
                    class="bg-[#002b57] text-white hover:bg-[#003c78]"
                    onclick={applyCreatePlanDraft}
                >
                    Apply To Form
                </Button>
            </div>
        </div>
    {/if}
{/snippet}

<AiDraftDialog
    bind:open={createAiOpen}
    bind:prompt={createAiPrompt}
    title="Generate Test Plan Draft"
    description="Describe the release scope, risk areas, or testing goals you want the QA agent to turn into a draft."
    processing={createAiProcessing}
    error={createAiError}
    warnings={createAiWarnings}
    contextItems={createAiContextItems}
    hasDraft={createAiDraft !== null}
    generateLabel="Generate Draft"
    ongenerate={generateCreatePlanDraft}
    preview={createAiPreview}
/>

<Dialog
    open={Boolean(deletingPlan)}
    onOpenChange={(open) => {
        if (!open) {
            deletingPlan = null;
        }
    }}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Test Plan</DialogTitle>
        <DialogDescription>
            This will permanently remove the test plan from this project.
        </DialogDescription>

        {#if deletingPlan}
            <div
                class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <p class="font-semibold text-[#071a2f]">{deletingPlan.name}</p>
                <p class="mt-1 text-sm text-slate-600">
                    {deletingPlan.project.name} - {deletingPlan.tester ??
                        'Unassigned'}
                </p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (deletingPlan = null)}
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
                    Delete Test Plan
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>
