<script module lang="ts">
    import { index as issuesIndexRoute } from '@/routes/issues';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Issues',
                href: issuesIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AlertOctagon from 'lucide-svelte/icons/alert-octagon';
    import CircleGauge from 'lucide-svelte/icons/circle-gauge';
    import Eye from 'lucide-svelte/icons/eye';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import AiDraftDialog from '@/components/ai/AiDraftDialog.svelte';
    import IssueForm from '@/components/issues/IssueForm.svelte';
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
        AiDraftApiError,
        generateAiDraft,
        summarizeAiSourceContext,
        type IssueAiDraft,
        type IssueAiDraftSet,
    } from '@/lib/api/ai';
    import {
        createIssue,
        deleteIssue,
        fetchIssues,
        IssueApiError,
        updateIssue,
    } from '@/lib/api/issues';
    import type {
        IssueFilters,
        IssueFormValues,
        IssueItem,
        IssueListResponse,
        IssuePriority,
        IssueSeverity,
        IssueStatus,
    } from '@/lib/api/issues';
    import { show as issuesShow } from '@/routes/issues';

    let {
        initialPayload,
    }: {
        initialPayload: IssueListResponse;
    } = $props();

    const clonePayload = (response: IssueListResponse): IssueListResponse => ({
        ...response,
        data: response.data.map((issue) => ({
            ...issue,
            project: { ...issue.project },
            test_plan: { ...issue.test_plan },
            test_run: {
                ...issue.test_run,
                test_plan: { ...issue.test_run.test_plan },
            },
        })),
        meta: {
            ...response.meta,
            links: response.meta.links.map((link) => ({ ...link })),
        },
        stats: { ...response.stats },
        filters: { ...response.filters },
        options: {
            hasActiveProject: response.options.hasActiveProject,
            testPlans: response.options.testPlans.map((testPlan) => ({
                ...testPlan,
            })),
            testRuns: response.options.testRuns.map((testRun) => ({
                ...testRun,
                test_plan: { ...testRun.test_plan },
            })),
            priorities: [...response.options.priorities],
            severities: [...response.options.severities],
            statuses: [...response.options.statuses],
            environments: [...response.options.environments],
            defectTypes: [...response.options.defectTypes],
        },
    });

    const createDefaultValues = (
        response: IssueListResponse,
    ): IssueFormValues => ({
        title: '',
        description: '',
        expected_result: '',
        actual_result: '',
        priority:
            response.options.priorities.find(
                (priority) => priority === 'Medium',
            ) ??
            response.options.priorities[0] ??
            'Medium',
        severity:
            response.options.severities.find(
                (severity) => severity === 'Medium',
            ) ??
            response.options.severities[0] ??
            'Medium',
        status:
            response.options.statuses.find((status) => status === 'Open') ??
            response.options.statuses[0] ??
            'Open',
        reporter: '',
        assignee: '',
        environment:
            response.options.environments.find(
                (environment) => environment === 'Development',
            ) ??
            response.options.environments[0] ??
            '',
        defect_type:
            response.options.defectTypes.find(
                (defectType) => defectType === 'Functional',
            ) ??
            response.options.defectTypes[0] ??
            '',
        test_plan_id: null,
        test_run_id: null,
    });

    const getStartingPayload = (): IssueListResponse =>
        clonePayload(initialPayload);

    const startingPayload = getStartingPayload();

    let payload = $state(startingPayload);
    let filters = $state<IssueFilters>({ ...startingPayload.filters });
    let loading = $state(false);
    let createOpen = $state(false);
    let editingIssue = $state<IssueItem | null>(null);
    let deletingIssue = $state<IssueItem | null>(null);
    let createErrors = $state<Record<string, string>>({});
    let editErrors = $state<Record<string, string>>({});
    let createProcessing = $state(false);
    let editProcessing = $state(false);
    let deleteProcessing = $state(false);
    let statusUpdatingIssueId = $state<number | null>(null);
    let createAiOpen = $state(false);
    let createAiPrompt = $state('');
    let createAiProcessing = $state(false);
    let createAiError = $state<string | null>(null);
    let createAiWarnings = $state<string[]>([]);
    let createAiContextItems = $state<string[]>([]);
    let createAiDraftSet = $state<IssueAiDraftSet | null>(null);
    let createValues = $state<IssueFormValues>(
        createDefaultValues(startingPayload),
    );
    let editValues = $state<IssueFormValues>(
        createDefaultValues(startingPayload),
    );

    const pageNumbers = $derived.by(() =>
        Array.from({ length: payload.meta.last_page }, (_, index) => index + 1),
    );

    const statCards = $derived([
        {
            title: 'Total Active Issues',
            value: String(payload.stats.totalActiveIssues),
            caption: 'Open workload in the active project',
            icon: AlertOctagon,
            iconClass: 'bg-[#eef5ff] text-[#002b57]',
        },
        {
            title: 'Critical Severity',
            value: String(payload.stats.criticalIssues),
            caption: 'Highest-risk unresolved defects',
            icon: ShieldAlert,
            iconClass: 'bg-[#fff1f2] text-[#d11a2a]',
        },
        {
            title: 'Resolution Velocity',
            value:
                payload.stats.resolutionVelocityDays === null
                    ? '--'
                    : `${payload.stats.resolutionVelocityDays.toFixed(1)} Days`,
            caption:
                payload.stats.resolutionVelocityDays === null
                    ? 'No solved issues yet'
                    : 'Average days from create to solve',
            icon: CircleGauge,
            iconClass: 'bg-[#f2f8f3] text-[#166534]',
        },
    ]);

    const severityClass = (severity: IssueSeverity): string => {
        const classes: Record<IssueSeverity, string> = {
            Low: 'border-slate-200 bg-slate-100 text-slate-700',
            Medium: 'border-amber-200 bg-amber-100 text-amber-800',
            High: 'border-rose-200 bg-rose-100 text-rose-800',
            Critical: 'border-red-300 bg-red-100 text-red-800',
        };

        return classes[severity];
    };

    const statusClass = (status: IssueStatus): string => {
        const classes: Record<IssueStatus, string> = {
            Open: 'bg-sky-50 text-sky-700',
            'In Investigation': 'bg-violet-50 text-violet-700',
            'In Testing': 'bg-amber-50 text-amber-700',
            Solved: 'bg-emerald-50 text-emerald-700',
            Deferred: 'bg-slate-100 text-slate-700',
        };

        return classes[status];
    };

    const priorityClass = (priority: IssuePriority): string => {
        const classes: Record<IssuePriority, string> = {
            Low: 'border-slate-200 bg-slate-100 text-slate-700',
            Medium: 'border-amber-200 bg-amber-100 text-amber-800',
            High: 'border-rose-200 bg-rose-100 text-rose-800',
            Urgent: 'border-red-300 bg-red-100 text-red-800',
        };

        return classes[priority];
    };

    const formatDateTime = (value: string | null): string => {
        if (!value) {
            return '-';
        }

        return new Intl.DateTimeFormat('en', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    };

    const syncFromResponse = (response: IssueListResponse): void => {
        payload = clonePayload(response);
        filters = { ...response.filters };
    };

    const reload = async (
        nextFilters: Partial<IssueFilters> = {},
    ): Promise<void> => {
        loading = true;

        try {
            const response = await fetchIssues({
                ...filters,
                ...nextFilters,
            });

            syncFromResponse(response);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load issues.',
            );
        } finally {
            loading = false;
        }
    };

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
        createAiDraftSet = null;
    };

    const issueToFormValues = (
        issue: IssueItem,
        status: IssueStatus = issue.status,
    ): IssueFormValues => {
        const environment = issue.environment as IssueFormValues['environment'];
        const defectType = issue.defect_type as IssueFormValues['defect_type'];

        return {
            title: issue.title ?? issue.issue_key,
            description: issue.description,
            expected_result: issue.expected_result ?? '',
            actual_result: issue.actual_result ?? '',
            priority: issue.priority,
            severity: issue.severity,
            status,
            reporter: issue.reporter,
            assignee: issue.assignee ?? '',
            environment:
                environment &&
                payload.options.environments.includes(environment)
                    ? environment
                    : '',
            defect_type:
                defectType && payload.options.defectTypes.includes(defectType)
                    ? defectType
                    : '',
            test_plan_id: issue.test_plan.id,
            test_run_id: issue.test_run.id,
        };
    };

    const hydrateEditForm = (issue: IssueItem): void => {
        editValues = issueToFormValues(issue);
        editErrors = {};
    };

    const updateIssueStatus = async (
        issue: IssueItem,
        status: IssueStatus,
    ): Promise<void> => {
        if (issue.status === status || statusUpdatingIssueId !== null) {
            return;
        }

        statusUpdatingIssueId = issue.id;

        try {
            await updateIssue(issue.id, issueToFormValues(issue, status));
            toast.success('Issue status updated.');
            await reload({ page: filters.page });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to update issue status.',
            );
        } finally {
            statusUpdatingIssueId = null;
        }
    };

    const submitCreate = async (): Promise<void> => {
        createProcessing = true;
        createErrors = {};

        try {
            await createIssue(createValues);
            toast.success('Issue created.');
            createOpen = false;
            resetCreateForm();
            await reload({ page: 1 });
        } catch (error) {
            if (error instanceof IssueApiError && error.status === 422) {
                createErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to create the issue.',
                );
            }
        } finally {
            createProcessing = false;
        }
    };

    const submitUpdate = async (): Promise<void> => {
        if (!editingIssue) {
            return;
        }

        editProcessing = true;
        editErrors = {};

        try {
            await updateIssue(editingIssue.id, editValues);
            toast.success('Issue updated.');
            editingIssue = null;
            await reload({ page: filters.page });
        } catch (error) {
            if (error instanceof IssueApiError && error.status === 422) {
                editErrors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to update the issue.',
                );
            }
        } finally {
            editProcessing = false;
        }
    };

    const submitDelete = async (): Promise<void> => {
        if (!deletingIssue) {
            return;
        }

        deleteProcessing = true;

        try {
            await deleteIssue(deletingIssue.id);
            toast.success('Issue deleted.');
            deletingIssue = null;
            await reload({ page: filters.page });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to delete the issue.',
            );
        } finally {
            deleteProcessing = false;
        }
    };

    const onFilterChange = async (
        nextFilters: Partial<IssueFilters>,
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

    const applyIssueDraft = (draft: IssueAiDraft): void => {
        createValues = {
            title: draft.title,
            description: draft.description,
            expected_result: draft.expected_result ?? '',
            actual_result: draft.actual_result ?? '',
            priority: draft.priority,
            severity: draft.severity,
            status: draft.status,
            reporter: draft.reporter,
            assignee: draft.assignee ?? '',
            environment: draft.environment ?? '',
            defect_type: draft.defect_type ?? '',
            test_plan_id: draft.test_plan_id,
            test_run_id: draft.test_run_id,
        };
        createAiOpen = false;
        toast.success('AI draft applied to the issue form.');
    };

    const generateCreateIssueDraft = async (): Promise<void> => {
        createAiProcessing = true;
        createAiError = null;

        try {
            const response = await generateAiDraft<IssueAiDraftSet>({
                artifact_type: 'issues',
                prompt: createAiPrompt,
                test_plan_id: createValues.test_plan_id,
                test_run_id: createValues.test_run_id,
                count: 1,
            });

            createAiDraftSet = response.draft;
            createAiWarnings = response.warnings;
            createAiContextItems = summarizeAiSourceContext(
                response.source_context,
            );
        } catch (error) {
            if (error instanceof AiDraftApiError && error.status === 422) {
                createAiError =
                    Object.values(error.errors)[0] ??
                    'Unable to generate an issue draft.';
            } else {
                createAiError =
                    error instanceof Error
                        ? error.message
                        : 'Unable to generate an issue draft.';
            }
        } finally {
            createAiProcessing = false;
        }
    };
</script>

<AppHead title="Issues" />

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-[#071a2f]">
                    Issues & Bug Tracking
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Manage and monitor technical debt across the active testing
                    workspace.
                </p>
            </div>

            <Button
                class="bg-[#002b57] px-5 text-white shadow-sm hover:bg-[#003c78]"
                disabled={!payload.options.hasActiveProject}
                onclick={() => {
                    resetCreateForm();
                    resetCreateAi();
                    createOpen = true;
                }}
            >
                <Plus class="size-4" />
                Create Issue
            </Button>
        </div>

        {#if !payload.options.hasActiveProject}
            <section
                class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center shadow-sm"
            >
                <p
                    class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500"
                >
                    No Active Project
                </p>
                <p class="mt-3 text-lg font-semibold text-[#071a2f]">
                    Select a project from the Projects page to start tracking
                    issues.
                </p>
            </section>
        {/if}

        <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1.5fr]">
            {#each statCards as stat (stat.title)}
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
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
                            <p class="mt-3 text-sm text-slate-500">
                                {stat.caption}
                            </p>
                        </div>
                        <span
                            class={`flex size-11 items-center justify-center rounded-2xl ${stat.iconClass}`}
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
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative min-w-[15rem]">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            id="issues-search"
                            aria-label="Search issues"
                            bind:value={filters.search}
                            class="border-slate-300 bg-white pl-9 text-[#071a2f] placeholder:text-slate-400"
                            placeholder="Search issues, reporters, environments"
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
                        id="issues-severity-filter"
                        aria-label="Filter issues by severity"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={filters.severity}
                        onchange={(event) =>
                            onFilterChange({
                                severity: (
                                    event.currentTarget as HTMLSelectElement
                                ).value,
                            })}
                    >
                        <option value="all">All Severities</option>
                        {#each payload.options.severities as severity (severity)}
                            <option value={severity}>{severity}</option>
                        {/each}
                    </select>

                    <select
                        id="issues-status-filter"
                        aria-label="Filter issues by status"
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
                </div>

                <p class="text-sm text-slate-600">
                    Showing {payload.meta.from ?? 0}-{payload.meta.to ?? 0} of {payload
                        .meta.total} issues
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    id="issues-table"
                    aria-label="Issues"
                    class="min-w-full divide-y divide-slate-200"
                >
                    <thead class="bg-slate-50/80">
                        <tr
                            class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            <th class="px-4 py-4 lg:px-6">ID</th>
                            <th class="px-4 py-4">Issue</th>
                            <th class="px-4 py-4 text-center">Priority</th>
                            <th class="px-4 py-4 text-center">Severity</th>
                            <th class="px-4 py-4 text-center">Status</th>
                            <th class="px-4 py-4">Closed Date</th>
                            <th class="px-4 py-4 text-right lg:px-6">Actions</th
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
                                        Loading issues...
                                    </div>
                                </td>
                            </tr>
                        {:else if payload.data.length === 0}
                            <tr>
                                <td
                                    colspan="7"
                                    class="px-6 py-16 text-center text-sm text-slate-500"
                                >
                                    {#if payload.options.hasActiveProject}
                                        No issues matched the current filters.
                                    {:else}
                                        Select an active project to see issue
                                        data.
                                    {/if}
                                </td>
                            </tr>
                        {:else}
                            {#each payload.data as issue (issue.id)}
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td
                                        class="px-4 py-4 font-semibold text-[#002b57] lg:px-6"
                                    >
                                        {issue.issue_key}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div>
                                            <p
                                                class="max-w-xl font-semibold text-[#071a2f]"
                                            >
                                                {issue.title ?? issue.issue_key}
                                            </p>
                                            <p
                                                class="hidden"
                                                aria-hidden="true"
                                            >
                                                {#if issue.assignee}
                                                    • Assignee: {issue.assignee}
                                                {/if}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${priorityClass(
                                                issue.priority,
                                            )}`}
                                        >
                                            {issue.priority}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${severityClass(
                                                issue.severity,
                                            )}`}
                                        >
                                            {issue.severity}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div
                                            class="inline-flex items-center justify-center gap-2"
                                        >
                                            <select
                                                id={`issue-${issue.id}-status`}
                                                class={`h-9 rounded-full border px-3 text-sm font-semibold outline-none transition focus:ring-3 focus:ring-[#002b57]/15 disabled:cursor-not-allowed disabled:opacity-60 ${statusClass(
                                                    issue.status,
                                                )}`}
                                                value={issue.status}
                                                disabled={statusUpdatingIssueId !==
                                                    null}
                                                aria-label={`Change status for ${issue.issue_key}`}
                                                onchange={(event) =>
                                                    updateIssueStatus(
                                                        issue,
                                                        (
                                                            event.currentTarget as HTMLSelectElement
                                                        ).value as IssueStatus,
                                                    )}
                                            >
                                                {#each payload.options.statuses as status (status)}
                                                    <option value={status}>
                                                        {status}
                                                    </option>
                                                {/each}
                                            </select>
                                            {#if statusUpdatingIssueId === issue.id}
                                                <Spinner />
                                            {/if}
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-4 text-sm text-slate-600"
                                    >
                                        {formatDateTime(issue.closed_at)}
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
                                                        href={issuesShow(
                                                            issue.id,
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
                                                onclick={() => {
                                                    editingIssue = issue;
                                                    hydrateEditForm(issue);
                                                }}
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
                                                    (deletingIssue = issue)}
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
                        id="issues-rows-per-page"
                        aria-label="Issues rows per page"
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
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-4xl"
    >
        <DialogTitle>Create Issue</DialogTitle>
        <DialogDescription>
            Add a new defect to the active project backlog and link it to the
            relevant plan or test run when needed.
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
                Generate Issue
            </Button>
        </div>
        <IssueForm
            values={createValues}
            errors={createErrors}
            processing={createProcessing}
            testPlans={payload.options.testPlans}
            testRuns={payload.options.testRuns}
            priorities={payload.options.priorities}
            severities={payload.options.severities}
            statuses={payload.options.statuses}
            environments={payload.options.environments}
            defectTypes={payload.options.defectTypes}
            submitLabel="Create Issue"
            onsubmit={submitCreate}
            oncancel={() => {
                createOpen = false;
                resetCreateForm();
            }}
        />
    </DialogContent>
</Dialog>

{#snippet createIssueAiPreview()}
    {#if createAiDraftSet}
        {#each createAiDraftSet.issues as draft, index (`${draft.title}-${index}`)}
            <div class="grid gap-4 text-sm text-[#071a2f]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-semibold">{draft.title}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {draft.priority} • {draft.severity} • {draft.status}
                        </p>
                    </div>
                    <Button
                        type="button"
                        class="bg-[#002b57] text-white hover:bg-[#003c78]"
                        onclick={() => applyIssueDraft(draft)}
                    >
                        Apply To Form
                    </Button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">
                            Expected Result
                        </p>
                        <p>{draft.expected_result ?? 'Not provided'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">
                            Actual Result
                        </p>
                        <p>{draft.actual_result ?? 'Not provided'}</p>
                    </div>
                </div>

                <div class="grid gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">
                            Reproduction Steps
                        </p>
                        <p>{draft.description}</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">
                                Reporter
                            </p>
                            <p>{draft.reporter}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">
                                Assignee
                            </p>
                            <p>{draft.assignee ?? 'Not assigned'}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">
                                Environment
                            </p>
                            <p>{draft.environment ?? 'Not provided'}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">
                                Defect Type
                            </p>
                            <p>{draft.defect_type ?? 'Not provided'}</p>
                        </div>
                    </div>
                </div>
            </div>
        {/each}
    {/if}
{/snippet}

<AiDraftDialog
    bind:open={createAiOpen}
    bind:prompt={createAiPrompt}
    title="Generate Issue Draft"
    description="Describe the defect, reproduction path, and impact. The modal will preview the draft before anything is copied into the issue form."
    processing={createAiProcessing}
    error={createAiError}
    warnings={createAiWarnings}
    contextItems={createAiContextItems}
    hasDraft={createAiDraftSet !== null}
    generateLabel="Generate Draft"
    ongenerate={generateCreateIssueDraft}
    preview={createIssueAiPreview}
/>

<Dialog
    open={Boolean(editingIssue)}
    onOpenChange={(open) => {
        if (!open) {
            editingIssue = null;
            editErrors = {};
        }
    }}
>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-[#f8fbff] text-[#071a2f] sm:max-h-[90vh] sm:max-w-4xl"
    >
        <DialogTitle>Edit Issue</DialogTitle>
        <DialogDescription>
            Update the issue details, ownership, or linked testing artifacts.
        </DialogDescription>
        {#if editingIssue}
            <IssueForm
                values={editValues}
                errors={editErrors}
                processing={editProcessing}
                testPlans={payload.options.testPlans}
                testRuns={payload.options.testRuns}
                priorities={payload.options.priorities}
                severities={payload.options.severities}
                statuses={payload.options.statuses}
                environments={payload.options.environments}
                defectTypes={payload.options.defectTypes}
                submitLabel="Save Changes"
                onsubmit={submitUpdate}
                oncancel={() => {
                    editingIssue = null;
                    editErrors = {};
                }}
            />
        {/if}
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(deletingIssue)}
    onOpenChange={(open) => {
        if (!open) {
            deletingIssue = null;
        }
    }}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Issue</DialogTitle>
        <DialogDescription>
            This permanently removes the issue from the active backlog.
        </DialogDescription>

        {#if deletingIssue}
            <div
                class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <p class="font-semibold text-[#071a2f]">
                    {deletingIssue.issue_key}
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    {deletingIssue.title ?? deletingIssue.description}
                </p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (deletingIssue = null)}
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
                    Delete Issue
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>
