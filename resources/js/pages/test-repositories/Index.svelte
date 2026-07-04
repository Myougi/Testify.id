<script module lang="ts">
    import { index as testRepositoriesIndexRoute } from '@/routes/test-repositories';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Test Repositories',
                href: testRepositoriesIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';
    import Folder from 'lucide-svelte/icons/folder';
    import Info from 'lucide-svelte/icons/info';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import AiDraftDialog from '@/components/ai/AiDraftDialog.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import {
        AiDraftApiError,
        generateAiDraft,
        summarizeAiSourceContext,
    } from '@/lib/api/ai';
    import type { TestCaseAiDraftSet } from '@/lib/api/ai';
    import {
        bulkCreateTestCases,
        createTestRepositoryModule,
        deleteTestCase,
        deleteTestRepositoryModule,
        fetchTestCases,
        TestRepositoryApiError,
        updateTestRepositoryModule,
    } from '@/lib/api/testRepository';
    import type {
        TestCaseFormValues,
        TestCaseItem,
        TestCaseListResponse,
        TestRepositoryFilters,
        TestRepositoryModule,
        TestRepositoryModuleFormValues,
    } from '@/lib/api/testRepository';

    let {
        initialPayload,
    }: {
        initialPayload: TestCaseListResponse;
    } = $props();

    type ModuleWithDepth = TestRepositoryModule & {
        depth: number;
    };

    const clonePayload = (
        payload: TestCaseListResponse,
    ): TestCaseListResponse => ({
        ...payload,
        data: payload.data.map((testCase) => ({ ...testCase })),
        meta: {
            ...payload.meta,
            links: payload.meta.links.map((link) => ({ ...link })),
        },
        stats: { ...payload.stats },
        filters: { ...payload.filters },
        options: {
            projects: payload.options.projects.map((project) => ({
                ...project,
            })),
            modules: payload.options.modules.map((module) => ({ ...module })),
            priorities: [...payload.options.priorities],
            automationStatuses: [...payload.options.automationStatuses],
            behaviors: [...payload.options.behaviors],
            types: [...payload.options.types],
            aiGeneration: {
                generationLimit: payload.options.aiGeneration.generationLimit,
                hasActiveProject: payload.options.aiGeneration.hasActiveProject,
                activeProject: payload.options.aiGeneration.activeProject
                    ? { ...payload.options.aiGeneration.activeProject }
                    : null,
                testPlans: payload.options.aiGeneration.testPlans.map(
                    (testPlan) => ({
                        ...testPlan,
                    }),
                ),
                modules: payload.options.aiGeneration.modules.map((module) => ({
                    ...module,
                })),
            },
        },
    });

    const initialRepositoryPayload = (): TestCaseListResponse =>
        clonePayload(initialPayload);

    const startingPayload = initialRepositoryPayload();

    let payload = $state(startingPayload);
    let filters = $state<TestRepositoryFilters>({ ...startingPayload.filters });
    let loading = $state(false);
    let moduleDialogOpen = $state(false);
    let editingModule = $state<TestRepositoryModule | null>(null);
    let deletingModule = $state<TestRepositoryModule | null>(null);
    let deletingCase = $state<TestCaseItem | null>(null);
    let moduleProcessing = $state(false);
    let deleteProcessing = $state(false);
    let moduleErrors = $state<Record<string, string>>({});
    let aiOpen = $state(false);
    let aiPrompt = $state('');
    let aiSelectedTestPlanId = $state<number | null>(null);
    let aiSelectedModuleId = $state<number | null>(null);
    let aiProcessing = $state(false);
    let aiSaving = $state(false);
    let aiError = $state<string | null>(null);
    let aiWarnings = $state<string[]>([]);
    let aiContextItems = $state<string[]>([]);
    let aiDraftSet = $state<TestCaseAiDraftSet | null>(null);
    let moduleValues = $state<TestRepositoryModuleFormValues>({
        project_id: startingPayload.options.projects[0]?.id ?? null,
        parent_id: null,
        name: '',
        key_prefix: '',
    });

    const childModules = (parentId: number | null): TestRepositoryModule[] =>
        payload.options.modules
            .filter((module) => module.parent_id === parentId)
            .sort(
                (first, second) =>
                    first.sort_order - second.sort_order ||
                    first.name.localeCompare(second.name),
            );

    const flattenModules = (
        parentId: number | null = null,
        depth = 0,
    ): ModuleWithDepth[] =>
        childModules(parentId).flatMap((module) => [
            { ...module, depth },
            ...flattenModules(module.id, depth + 1),
        ]);

    const flatModules = $derived(flattenModules());
    const selectedModule = $derived(
        payload.options.modules.find(
            (module) => module.id === filters.module_id,
        ) ?? null,
    );
    const aiGeneration = $derived(payload.options.aiGeneration);
    const canCreateCase = $derived(payload.options.modules.length > 0);
    const canGenerateCases = $derived(
        aiGeneration.hasActiveProject &&
            aiGeneration.testPlans.length > 0 &&
            aiGeneration.modules.length > 0,
    );
    const pageNumbers = $derived.by(() =>
        Array.from({ length: payload.meta.last_page }, (_, index) => index + 1),
    );

    const priorityClass = (priority: string): string => {
        if (priority === 'Urgent') {
            return 'bg-red-100 text-red-800';
        }

        if (priority === 'High') {
            return 'bg-rose-100 text-rose-700';
        }

        if (priority === 'Medium') {
            return 'bg-amber-100 text-amber-700';
        }

        return 'bg-slate-200 text-slate-600';
    };

    const automationDotClass = (status: string): string =>
        status === 'Automated' ? 'bg-[#002b57]' : 'bg-slate-400';

    const formatDate = (isoDate: string | null): string => {
        if (!isoDate) {
            return 'Not set';
        }

        return new Intl.DateTimeFormat('en', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(isoDate));
    };

    const syncFromResponse = (response: TestCaseListResponse): void => {
        payload = clonePayload(response);
        filters = { ...response.filters };
    };

    const reload = async (
        nextFilters: Partial<TestRepositoryFilters> = {},
    ): Promise<void> => {
        loading = true;

        try {
            const response = await fetchTestCases({
                ...filters,
                ...nextFilters,
            });

            syncFromResponse(response);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load test cases.',
            );
        } finally {
            loading = false;
        }
    };

    const openCreateModuleDialog = (): void => {
        moduleValues = {
            project_id:
                selectedModule?.project_id ??
                payload.options.projects[0]?.id ??
                null,
            parent_id: selectedModule?.id ?? null,
            name: '',
            key_prefix: '',
        };
        editingModule = null;
        moduleErrors = {};
        moduleDialogOpen = true;
    };

    const openEditModuleDialog = (module: TestRepositoryModule): void => {
        moduleValues = {
            project_id: module.project_id,
            parent_id: module.parent_id,
            name: module.name,
            key_prefix: module.key_prefix,
        };
        editingModule = module;
        moduleErrors = {};
        moduleDialogOpen = true;
    };

    const submitModule = async (): Promise<void> => {
        moduleProcessing = true;
        moduleErrors = {};

        try {
            if (editingModule) {
                await updateTestRepositoryModule(
                    editingModule.id,
                    moduleValues,
                );
                toast.success('Folder updated.');
            } else {
                await createTestRepositoryModule(moduleValues);
                toast.success('Folder created.');
            }

            moduleDialogOpen = false;
            editingModule = null;
            await reload({ page: 1 });
        } catch (error) {
            if (
                error instanceof TestRepositoryApiError &&
                error.status === 422
            ) {
                moduleErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to save the folder.',
                );
            }
        } finally {
            moduleProcessing = false;
        }
    };

    const submitDeleteModule = async (): Promise<void> => {
        if (!deletingModule) {
            return;
        }

        deleteProcessing = true;

        try {
            await deleteTestRepositoryModule(deletingModule.id);
            toast.success('Folder deleted.');
            deletingModule = null;
            await reload({ page: 1, module_id: null });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to delete the folder.',
            );
        } finally {
            deleteProcessing = false;
        }
    };

    const submitDeleteCase = async (): Promise<void> => {
        if (!deletingCase) {
            return;
        }

        deleteProcessing = true;

        try {
            await deleteTestCase(deletingCase.id);
            toast.success('Test case deleted.');
            deletingCase = null;
            await reload({ page: filters.page });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to delete the test case.',
            );
        } finally {
            deleteProcessing = false;
        }
    };

    const selectModule = async (moduleId: number | null): Promise<void> => {
        filters.module_id = moduleId;
        await reload({ page: 1, module_id: moduleId });
    };

    const goToPage = async (page: number): Promise<void> => {
        if (page < 1 || page > payload.meta.last_page || loading) {
            return;
        }

        filters.page = page;
        await reload({ page });
    };

    const resetAiState = (): void => {
        aiPrompt = '';
        aiSelectedTestPlanId = null;
        aiSelectedModuleId =
            filters.module_id &&
            aiGeneration.modules.some(
                (module) => module.id === filters.module_id,
            )
                ? filters.module_id
                : null;
        aiProcessing = false;
        aiSaving = false;
        aiError = null;
        aiWarnings = [];
        aiContextItems = [];
        aiDraftSet = null;
    };

    const openGenerateCasesDialog = (): void => {
        resetAiState();
        aiOpen = true;
    };

    const toGeneratedCaseValues = (
        caseDraft: TestCaseAiDraftSet['cases'][number],
    ): TestCaseFormValues | null => {
        const activeProjectId = aiGeneration.activeProject?.id ?? null;

        if (activeProjectId === null || aiSelectedModuleId === null) {
            return null;
        }

        return {
            project_id: activeProjectId,
            module_id: aiSelectedModuleId,
            title: caseDraft.title,
            priority: caseDraft.priority,
            automation_status: caseDraft.automation_status,
            test_behavior: caseDraft.test_behavior,
            test_type: caseDraft.test_type,
            preconditions: caseDraft.preconditions ?? '',
            steps: caseDraft.steps.map((step) => ({
                action: step.action,
                expected_result: step.expected_result,
            })),
        };
    };

    const generateCaseDrafts = async (): Promise<void> => {
        if (
            !aiGeneration.hasActiveProject ||
            aiGeneration.activeProject === null
        ) {
            aiError =
                'Select an active project before generating AI test cases.';

            return;
        }

        if (aiSelectedTestPlanId === null) {
            aiError = 'Select a test plan to provide AI context.';

            return;
        }

        if (aiSelectedModuleId === null) {
            aiError =
                'Select a destination folder for the generated test cases.';

            return;
        }

        aiProcessing = true;
        aiError = null;

        try {
            const response = await generateAiDraft<TestCaseAiDraftSet>({
                artifact_type: 'test_cases',
                prompt: aiPrompt,
                project_id: aiGeneration.activeProject.id,
                test_plan_id: aiSelectedTestPlanId,
                module_id: aiSelectedModuleId,
            });

            aiDraftSet = response.draft;
            aiWarnings = response.warnings;
            aiContextItems = summarizeAiSourceContext(response.source_context);
        } catch (error) {
            if (error instanceof AiDraftApiError && error.status === 422) {
                aiError =
                    Object.values(error.errors)[0] ??
                    'Unable to generate test case drafts.';
            } else {
                aiError =
                    error instanceof Error
                        ? error.message
                        : 'Unable to generate test case drafts.';
            }
        } finally {
            aiProcessing = false;
        }
    };

    const confirmBulkCreate = async (): Promise<void> => {
        if (
            aiDraftSet === null ||
            aiGeneration.activeProject === null ||
            aiSelectedTestPlanId === null ||
            aiSelectedModuleId === null
        ) {
            return;
        }

        const cases = aiDraftSet.cases
            .map(toGeneratedCaseValues)
            .filter(
                (caseValues): caseValues is TestCaseFormValues =>
                    caseValues !== null,
            );

        if (cases.length === 0) {
            aiError = 'No valid generated test cases are available to save.';

            return;
        }

        aiSaving = true;
        aiError = null;

        try {
            const response = await bulkCreateTestCases({
                project_id: aiGeneration.activeProject.id,
                module_id: aiSelectedModuleId,
                test_plan_id: aiSelectedTestPlanId,
                cases,
            });

            aiOpen = false;
            toast.success(
                `${response.meta.created} AI-generated test cases created.`,
            );
            filters.module_id = aiSelectedModuleId;
            await reload({
                page: 1,
                module_id: aiSelectedModuleId,
            });
        } catch (error) {
            if (
                error instanceof TestRepositoryApiError &&
                error.status === 422
            ) {
                aiError =
                    Object.values(error.errors)[0] ??
                    'Unable to create the generated test cases.';
            } else {
                aiError =
                    error instanceof Error
                        ? error.message
                        : 'Unable to create the generated test cases.';
            }
        } finally {
            aiSaving = false;
        }
    };
</script>

<AppHead title="Test Repository" />

<div class="flex min-h-[calc(100vh-4rem)] bg-[#f4f7fa] text-[#061b33]">
    <aside
        class="hidden w-72 shrink-0 border-r border-slate-200 bg-white xl:block"
    >
        <div
            class="flex items-center justify-between border-b border-slate-200 px-4 py-4"
        >
            <p
                class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500"
            >
                Repository Modules
            </p>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="New folder"
                onclick={openCreateModuleDialog}
            >
                <Plus class="size-4" />
            </Button>
        </div>

        <div class="space-y-1 px-3 py-3">
            <button
                id="repository-all-modules"
                type="button"
                class={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium ${
                    filters.module_id === null
                        ? 'bg-[#edf4f8] text-[#002b57]'
                        : 'text-slate-600 hover:bg-slate-50'
                }`}
                onclick={() => selectModule(null)}
            >
                <span class="flex items-center gap-2">
                    <ClipboardList class="size-4" />
                    All test cases
                </span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">
                    {payload.stats.totalCases}
                </span>
            </button>

            {#each flatModules as module (module.id)}
                <button
                    id={`repository-module-${module.id}`}
                    type="button"
                    class={`flex w-full items-center justify-between rounded-md py-2 pr-3 text-left text-sm ${
                        filters.module_id === module.id
                            ? 'bg-[#edf4f8] font-semibold text-[#002b57]'
                            : 'text-slate-600 hover:bg-slate-50'
                    }`}
                    style={`padding-left: ${0.75 + module.depth * 1.15}rem`}
                    onclick={() => selectModule(module.id)}
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <Folder class="size-4 shrink-0" />
                        <span class="truncate">{module.name}</span>
                    </span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">
                        {module.test_cases_count ?? 0}
                    </span>
                </button>
            {/each}
        </div>
    </aside>

    <main class="min-w-0 flex-1 px-4 py-5 sm:px-6 lg:px-8">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <div
                        class="mb-2 flex items-center gap-2 text-xs font-semibold text-slate-500"
                    >
                        <span>Projects</span>
                        <ChevronRight class="size-3" />
                        <span
                            >{aiGeneration.activeProject?.name ??
                                'No Active Project'}</span
                        >
                        <ChevronRight class="size-3" />
                        <span class="text-[#002b57]">
                            {selectedModule?.name ?? 'All Repository'}
                        </span>
                    </div>
                    <h1
                        class="text-3xl font-bold tracking-normal text-[#071a2f]"
                    >
                        {selectedModule?.name ?? 'Test Repository'}
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Showing {payload.meta.total} test cases in this scope
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-12 border-slate-200 bg-white px-5 text-[#071a2f] hover:bg-slate-50"
                        disabled={!canGenerateCases}
                        title={!aiGeneration.hasActiveProject
                            ? 'Select an active project to generate cases.'
                            : undefined}
                        onclick={openGenerateCasesDialog}
                    >
                        <Sparkles class="size-4" />
                        Generate Cases
                    </Button>
                    {#if selectedModule}
                        <Button
                            type="button"
                            variant="outline"
                            class="h-12 border-slate-200 bg-white px-4"
                            onclick={() => openEditModuleDialog(selectedModule)}
                        >
                            <Pencil class="size-4" />
                            Rename
                        </Button>
                    {/if}
                    <Button
                        type="button"
                        class="h-12 bg-[#002b57] px-5 text-white hover:bg-[#003c78]"
                        disabled={!canCreateCase}
                        onclick={() => {
                            const query = filters.module_id
                                ? `?module_id=${filters.module_id}`
                                : '';
                            router.visit(
                                `/test-repositories/test-cases/create${query}`,
                            );
                        }}
                    >
                        <Plus class="size-4" />
                        Add Test Case
                    </Button>
                </div>
            </div>

            <section class="grid gap-4 md:grid-cols-5">
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Total
                    </p>
                    <p class="mt-2 text-2xl font-bold text-[#071a2f]">
                        {payload.stats.totalCases}
                    </p>
                </article>
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Automated
                    </p>
                    <p class="mt-2 text-2xl font-bold text-[#071a2f]">
                        {payload.stats.automatedCases}
                    </p>
                </article>
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Manual
                    </p>
                    <p class="mt-2 text-2xl font-bold text-[#071a2f]">
                        {payload.stats.manualCases}
                    </p>
                </article>
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Positive
                    </p>
                    <p class="mt-2 text-2xl font-bold text-[#071a2f]">
                        {payload.stats.positiveCases}
                    </p>
                </article>
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Negative
                    </p>
                    <p class="mt-2 text-2xl font-bold text-[#071a2f]">
                        {payload.stats.negativeCases}
                    </p>
                </article>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative min-w-[15rem]">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <Input
                                id="repository-search"
                                aria-label="Search test cases and folders"
                                bind:value={filters.search}
                                class="border-slate-300 bg-white pl-9 text-[#071a2f] placeholder:text-slate-400"
                                placeholder="Search cases or folders"
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
                            id="repository-priority-filter"
                            aria-label="Filter test cases by priority"
                            bind:value={filters.priority}
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            onchange={(event) =>
                                reload({
                                    page: 1,
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
                            id="repository-type-filter"
                            aria-label="Filter test cases by type"
                            bind:value={filters.test_type}
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            onchange={(event) =>
                                reload({
                                    page: 1,
                                    test_type: (
                                        event.currentTarget as HTMLSelectElement
                                    ).value,
                                })}
                        >
                            <option value="all">All Types</option>
                            {#each payload.options.types as type (type)}
                                <option value={type}>{type}</option>
                            {/each}
                        </select>

                        <Button
                            type="button"
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
                        Showing {payload.meta.from ?? 0}-{payload.meta.to ?? 0} of
                        {payload.meta.total} results
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table
                        id="repository-test-cases-table"
                        aria-label="Test repository cases"
                        class="min-w-full divide-y divide-slate-200"
                    >
                        <thead class="bg-slate-50/80">
                            <tr
                                class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                            >
                                <th class="w-12 px-4 py-4">
                                    <input
                                        id="repository-select-all-cases"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-[#002b57]"
                                        aria-label="Select all test cases"
                                    />
                                </th>
                                <th class="px-4 py-4">Title</th>
                                <th class="px-4 py-4 text-center">Priority</th>
                                <th class="px-4 py-4">Automation</th>
                                <th class="px-4 py-4">Behavior / Type</th>
                                <th class="px-4 py-4">Last Modified</th>
                                <th class="w-16 px-4 py-4 text-right"
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
                                            Loading test cases...
                                        </div>
                                    </td>
                                </tr>
                            {:else if payload.data.length === 0}
                                <tr>
                                    <td
                                        colspan="7"
                                        class="px-6 py-16 text-center text-sm text-slate-500"
                                    >
                                        No test cases matched the current
                                        filters.
                                    </td>
                                </tr>
                            {:else}
                                {#each payload.data as testCase (testCase.id)}
                                    <tr class="align-top hover:bg-slate-50/70">
                                        <td class="px-4 py-5">
                                            <input
                                                id={`repository-case-${testCase.id}-select`}
                                                type="checkbox"
                                                class="rounded border-slate-300 text-[#002b57]"
                                                aria-label={`Select ${testCase.title}`}
                                            />
                                        </td>
                                        <td class="px-4 py-5">
                                            <div class="space-y-1">
                                                <p
                                                    class="font-semibold text-[#002b57]"
                                                >
                                                    {testCase.title}
                                                </p>
                                                <p
                                                    class="text-xs text-slate-500"
                                                >
                                                    {testCase.case_key} - {testCase
                                                        .module.name}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-5 text-center">
                                            <span
                                                class={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${priorityClass(testCase.priority)}`}
                                            >
                                                {testCase.priority}
                                            </span>
                                        </td>
                                        <td class="px-4 py-5">
                                            <span
                                                class="inline-flex items-center gap-2 text-sm text-[#071a2f]"
                                            >
                                                <span
                                                    class={`size-2 rounded-full ${automationDotClass(testCase.automation_status)}`}
                                                ></span>
                                                {testCase.automation_status}
                                            </span>
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
                                                <span>{testCase.test_type}</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="px-4 py-5 text-sm text-slate-600"
                                        >
                                            {formatDate(testCase.updated_at)}
                                        </td>
                                        <td class="px-4 py-5 text-right">
                                            <div
                                                class="inline-flex items-center gap-1"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-9 text-[#002b57]"
                                                    aria-label="Edit test case"
                                                    onclick={() =>
                                                        router.visit(
                                                            `/test-repositories/test-cases/${testCase.id}/edit`,
                                                        )}
                                                >
                                                    <Pencil class="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-9 text-red-600 hover:bg-red-50 hover:text-red-700"
                                                    aria-label="Delete test case"
                                                    onclick={() =>
                                                        (deletingCase =
                                                            testCase)}
                                                >
                                                    <Trash2 class="size-4" />
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
                    class="flex flex-col gap-4 border-t border-slate-200 px-4 py-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex items-center gap-3 text-sm text-slate-600">
                        <span>Rows per page:</span>
                        <select
                            id="repository-rows-per-page"
                            aria-label="Test repository rows per page"
                            bind:value={filters.per_page}
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            onchange={(event) =>
                                reload({
                                    page: 1,
                                    per_page: Number(
                                        (
                                            event.currentTarget as HTMLSelectElement
                                        ).value,
                                    ),
                                })}
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
    </main>
</div>

{#snippet aiControls()}
    <div class="grid gap-4">
        <div
            class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600"
        >
            <Info class="mt-0.5 size-4 shrink-0 text-[#002b57]" />
            <p>
                AI can generate up to {aiGeneration.generationLimit} test cases per
                request. Specify a number in your prompt for an exact count.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="repository-ai-test-plan">Test Plan</Label>
                <select
                    id="repository-ai-test-plan"
                    class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    value={aiSelectedTestPlanId ?? ''}
                    onchange={(event) => {
                        aiSelectedTestPlanId =
                            Number(
                                (event.currentTarget as HTMLSelectElement)
                                    .value,
                            ) || null;
                    }}
                >
                    <option value="">Select test plan</option>
                    {#each aiGeneration.testPlans as testPlan (testPlan.id)}
                        <option value={testPlan.id}>{testPlan.name}</option>
                    {/each}
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="repository-ai-module">Destination Folder</Label>
                <select
                    id="repository-ai-module"
                    class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    value={aiSelectedModuleId ?? ''}
                    onchange={(event) => {
                        aiSelectedModuleId =
                            Number(
                                (event.currentTarget as HTMLSelectElement)
                                    .value,
                            ) || null;
                    }}
                >
                    <option value="">Select destination folder</option>
                    {#each aiGeneration.modules as module (module.id)}
                        <option value={module.id}>{module.name}</option>
                    {/each}
                </select>
            </div>
        </div>
    </div>
{/snippet}

{#snippet aiPreview()}
    {#if aiDraftSet}
        <div class="grid gap-4">
            {#each aiDraftSet.cases as caseDraft, index (`${caseDraft.title}-${index}`)}
                <article
                    class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-[#071a2f]"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <p class="text-base font-semibold">
                                {caseDraft.title}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {caseDraft.priority} • {caseDraft.automation_status}
                                • {caseDraft.test_behavior} • {caseDraft.test_type}
                            </p>
                        </div>
                        <span
                            class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                        >
                            {aiGeneration.modules.find(
                                (module) => module.id === aiSelectedModuleId,
                            )?.name ?? 'Selected folder'}
                        </span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <p
                                class="text-xs font-semibold uppercase text-slate-500"
                            >
                                Preconditions
                            </p>
                            <p>{caseDraft.preconditions ?? 'Not provided'}</p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-semibold uppercase text-slate-500"
                            >
                                Test Plan Context
                            </p>
                            <p>
                                {aiGeneration.testPlans.find(
                                    (testPlan) =>
                                        testPlan.id === aiSelectedTestPlanId,
                                )?.name ?? 'Not selected'}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <p
                            class="text-xs font-semibold uppercase text-slate-500"
                        >
                            Steps
                        </p>
                        {#if caseDraft.steps.length === 0}
                            <div
                                class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-slate-500"
                            >
                                No steps generated.
                            </div>
                        {:else}
                            {#each caseDraft.steps as step, stepIndex (`${step.action}-${stepIndex}`)}
                                <div
                                    class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                                >
                                    <p class="font-medium text-[#071a2f]">
                                        {stepIndex + 1}. {step.action}
                                    </p>
                                    <p class="mt-1 text-slate-600">
                                        {step.expected_result}
                                    </p>
                                </div>
                            {/each}
                        {/if}
                    </div>
                </article>
            {/each}

            <div class="flex justify-end">
                <Button
                    type="button"
                    class="bg-[#002b57] text-white hover:bg-[#003c78]"
                    disabled={aiSaving}
                    onclick={confirmBulkCreate}
                >
                    {#if aiSaving}<Spinner />{/if}
                    Create {aiDraftSet.cases.length}
                    {aiDraftSet.cases.length === 1 ? 'Case' : 'Cases'}
                </Button>
            </div>
        </div>
    {/if}
{/snippet}

<AiDraftDialog
    bind:open={aiOpen}
    bind:prompt={aiPrompt}
    title="Generate Test Cases"
    description="Describe the coverage you want, choose a test plan for context, and pick the destination folder before creating the generated cases."
    processing={aiProcessing}
    error={aiError}
    warnings={aiWarnings}
    contextItems={aiContextItems}
    hasDraft={aiDraftSet !== null}
    generateLabel="Generate Drafts"
    ongenerate={generateCaseDrafts}
    controls={aiControls}
    preview={aiPreview}
/>

<Dialog bind:open={moduleDialogOpen}>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f] sm:max-w-xl">
        <DialogTitle
            >{editingModule ? 'Rename Folder' : 'New Folder'}</DialogTitle
        >
        <DialogDescription>
            Organize repository test cases into clear modules and subfolders.
        </DialogDescription>

        <form
            class="mt-5 grid gap-4"
            onsubmit={(event) => {
                event.preventDefault();
                void submitModule();
            }}
        >
            <div class="grid gap-2">
                <Label for="module_project">Project</Label>
                <select
                    id="module_project"
                    bind:value={moduleValues.project_id}
                    class="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                >
                    {#each payload.options.projects as project (project.id)}
                        <option value={project.id}>{project.name}</option>
                    {/each}
                </select>
                <InputError message={moduleErrors.project_id} />
            </div>

            <div class="grid gap-2">
                <Label for="module_parent">Parent Folder</Label>
                <select
                    id="module_parent"
                    class="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    value={moduleValues.parent_id ?? ''}
                    onchange={(event) => {
                        moduleValues.parent_id =
                            Number(
                                (event.currentTarget as HTMLSelectElement)
                                    .value,
                            ) || null;
                    }}
                >
                    <option value="">No parent</option>
                    {#each flatModules.filter((module) => module.id !== editingModule?.id) as module (module.id)}
                        <option value={module.id}>
                            {'- '.repeat(module.depth)}{module.name}
                        </option>
                    {/each}
                </select>
                <InputError message={moduleErrors.parent_id} />
            </div>

            <div class="grid gap-2">
                <Label for="module_name">Folder Name</Label>
                <Input
                    id="module_name"
                    bind:value={moduleValues.name}
                    class="border-slate-300 bg-white text-[#071a2f]"
                    placeholder="Authentication"
                    required
                />
                <InputError message={moduleErrors.name} />
            </div>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="module_key_prefix">Key Prefix</Label>
                    <Input
                        id="module_key_prefix"
                        bind:value={moduleValues.key_prefix}
                        class="border-slate-300 bg-white text-[#071a2f] uppercase"
                        placeholder="AUTH"
                        required
                    />
                    <InputError message={moduleErrors.key_prefix} />
                </div>
            </div>

            <InputError message={moduleErrors.module} />

            <div
                class="flex justify-between gap-3 border-t border-slate-200 pt-4"
            >
                {#if editingModule}
                    <Button
                        type="button"
                        variant="outline"
                        class="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                        onclick={() => {
                            deletingModule = editingModule;
                            moduleDialogOpen = false;
                        }}
                    >
                        <Trash2 class="size-4" />
                        Delete
                    </Button>
                {:else}
                    <span></span>
                {/if}

                <div class="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (moduleDialogOpen = false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="bg-[#002b57] text-white hover:bg-[#003c78]"
                        disabled={moduleProcessing}
                    >
                        {#if moduleProcessing}<Spinner />{/if}
                        {editingModule ? 'Save Folder' : 'Create Folder'}
                    </Button>
                </div>
            </div>
        </form>
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(deletingModule)}
    onOpenChange={(open) => {
        if (!open) {
            deletingModule = null;
        }
    }}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Folder</DialogTitle>
        <DialogDescription>
            Folders can only be deleted after their child folders and test cases
            are removed.
        </DialogDescription>

        {#if deletingModule}
            <div
                class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <p class="font-semibold text-[#071a2f]">
                    {deletingModule.name}
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    Prefix: {deletingModule.key_prefix}
                </p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (deletingModule = null)}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    disabled={deleteProcessing}
                    onclick={submitDeleteModule}
                >
                    {#if deleteProcessing}<Spinner />{/if}
                    Delete Folder
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(deletingCase)}
    onOpenChange={(open) => {
        if (!open) {
            deletingCase = null;
        }
    }}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Test Case</DialogTitle>
        <DialogDescription>
            This will permanently remove the test case and its ordered steps.
        </DialogDescription>

        {#if deletingCase}
            <div
                class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <p class="font-semibold text-[#071a2f]">{deletingCase.title}</p>
                <p class="mt-1 flex items-center gap-2 text-sm text-slate-600">
                    <CheckCircle2 class="size-4" />
                    {deletingCase.case_key} - {deletingCase.module.name}
                </p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (deletingCase = null)}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    disabled={deleteProcessing}
                    onclick={submitDeleteCase}
                >
                    {#if deleteProcessing}<Spinner />{/if}
                    Delete Test Case
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>
