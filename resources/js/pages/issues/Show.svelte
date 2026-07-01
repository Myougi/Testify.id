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
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Bug from 'lucide-svelte/icons/bug';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import ImageIcon from 'lucide-svelte/icons/image';
    import Link2 from 'lucide-svelte/icons/link-2';
    import UserRound from 'lucide-svelte/icons/user-round';
    import AppHead from '@/components/AppHead.svelte';
    import type {
        IssueItem,
        IssuePriority,
        IssueSeverity,
        IssueStatus,
    } from '@/lib/api/issues';
    import { index as issuesIndex } from '@/routes/issues';
    import { show as testExecutionsShow } from '@/routes/test-executions';
    import { show as testPlansShow } from '@/routes/test-plans';

    let {
        initialIssue,
    }: {
        initialIssue: IssueItem;
    } = $props();

    const priorityClass = (priority: IssuePriority): string => {
        const classes: Record<IssuePriority, string> = {
            Low: 'border-slate-200 bg-slate-100 text-slate-700',
            Medium: 'border-amber-200 bg-amber-100 text-amber-800',
            High: 'border-rose-200 bg-rose-100 text-rose-800',
            Urgent: 'border-red-300 bg-red-100 text-red-800',
        };

        return classes[priority];
    };

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
            Open: 'border-sky-200 bg-sky-50 text-sky-700',
            'In Investigation':
                'border-violet-200 bg-violet-50 text-violet-700',
            'In Testing': 'border-amber-200 bg-amber-50 text-amber-700',
            Solved: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            Deferred: 'border-slate-200 bg-slate-100 text-slate-700',
        };

        return classes[status];
    };

    const formatDate = (value: string | null): string => {
        if (!value) {
            return 'Not available';
        }

        return new Intl.DateTimeFormat('en', {
            dateStyle: 'medium',
        }).format(new Date(value));
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

    const valueOrFallback = (value: string | null): string =>
        value && value.trim() !== '' ? value : 'Not set';

    const formatFileSize = (size: number): string =>
        size >= 1024 * 1024
            ? `${(size / (1024 * 1024)).toFixed(1)} MB`
            : `${Math.max(1, Math.round(size / 1024))} KB`;
</script>

<AppHead title={initialIssue.title ?? initialIssue.issue_key} />

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div
        class="mx-auto grid w-full max-w-7xl gap-5 xl:grid-cols-[minmax(0,1fr)_320px]"
    >
        <main class="min-w-0 space-y-5">
            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <Link
                            href={issuesIndex()}
                            class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition-colors hover:text-[#002b57]"
                        >
                            <ArrowLeft class="size-4" />
                            Back to Issues
                        </Link>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span
                                class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${statusClass(
                                    initialIssue.status,
                                )}`}
                            >
                                {initialIssue.status}
                            </span>
                            <span
                                class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${severityClass(
                                    initialIssue.severity,
                                )}`}
                            >
                                {initialIssue.severity}
                            </span>
                            <span
                                class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${priorityClass(
                                    initialIssue.priority,
                                )}`}
                            >
                                {initialIssue.priority}
                            </span>
                        </div>

                        <h1
                            class="mt-3 text-2xl font-bold leading-tight tracking-normal text-[#071a2f] sm:text-3xl"
                        >
                            {initialIssue.title ?? initialIssue.issue_key}
                        </h1>
                        <p class="mt-2 text-sm font-semibold text-[#002b57]">
                            {initialIssue.issue_key}
                        </p>
                    </div>

                    <div
                        class="flex shrink-0 items-center gap-2 text-sm text-slate-500"
                    >
                        <CalendarDays class="size-4" />
                        <span
                            >Created {formatDate(initialIssue.created_at)}</span
                        >
                    </div>
                </div>

                <div
                    class="mt-5 border-l-4 border-[#002b57] bg-slate-50 px-4 py-4"
                >
                    <p class="text-sm leading-6 text-[#071a2f]">
                        {initialIssue.description}
                    </p>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex size-8 items-center justify-center rounded-lg bg-[#eff5fb] text-[#002b57]"
                        >
                            <Bug class="size-4" />
                        </span>
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Technical Details
                        </h2>
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5">
                    <div>
                        <h3
                            class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            Step to Reproduce
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {initialIssue.description}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <article
                            class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"
                        >
                            <h3
                                class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700"
                            >
                                Expected Result
                            </h3>
                            <p class="mt-3 text-sm leading-6 text-emerald-900">
                                {valueOrFallback(initialIssue.expected_result)}
                            </p>
                        </article>

                        <article
                            class="rounded-lg border border-red-200 bg-red-50 p-4"
                        >
                            <h3
                                class="text-xs font-semibold uppercase tracking-[0.16em] text-red-700"
                            >
                                Actual Result
                            </h3>
                            <p class="mt-3 text-sm leading-6 text-red-900">
                                {valueOrFallback(initialIssue.actual_result)}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            {#if initialIssue.image}
                <section
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-8 items-center justify-center rounded-lg bg-[#eff5fb] text-[#002b57]"
                            >
                                <ImageIcon class="size-4" />
                            </span>
                            <div>
                                <h2
                                    class="text-sm font-semibold text-[#071a2f]"
                                >
                                    Issue Evidence
                                </h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {initialIssue.image.name} - {formatFileSize(
                                        initialIssue.image.size,
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 sm:p-5">
                        <img
                            src={initialIssue.image.url}
                            alt={`Evidence for ${initialIssue.title ?? initialIssue.issue_key}`}
                            class="max-h-[36rem] w-full rounded-lg border border-slate-200 bg-white object-contain"
                        />
                    </div>
                </section>
            {/if}
        </main>

        <aside class="space-y-4 xl:sticky xl:top-5 xl:self-start">
            <section
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="flex items-center gap-2 border-b border-slate-200 pb-3"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-[#eff5fb] text-[#002b57]"
                    >
                        <UserRound class="size-4" />
                    </span>
                    <h2
                        class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Issue Attributes
                    </h2>
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Priority</dt>
                        <dd>
                            <span
                                class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${priorityClass(
                                    initialIssue.priority,
                                )}`}
                            >
                                {initialIssue.priority}
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Severity</dt>
                        <dd>
                            <span
                                class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${severityClass(
                                    initialIssue.severity,
                                )}`}
                            >
                                {initialIssue.severity}
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            <span
                                class={`inline-flex h-9 min-w-20 items-center justify-center rounded-full border px-3 text-sm font-semibold ${statusClass(
                                    initialIssue.status,
                                )}`}
                            >
                                {initialIssue.status}
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Environment</dt>
                        <dd class="text-right font-medium text-[#071a2f]">
                            {valueOrFallback(initialIssue.environment)}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Defect Type</dt>
                        <dd class="text-right font-medium text-[#071a2f]">
                            {valueOrFallback(initialIssue.defect_type)}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Reporter</dt>
                        <dd class="text-right font-medium text-[#071a2f]">
                            {initialIssue.reporter}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Assignee</dt>
                        <dd class="text-right font-medium text-[#071a2f]">
                            {valueOrFallback(initialIssue.assignee)}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Created Date</dt>
                        <dd class="text-right font-medium text-[#071a2f]">
                            {formatDateTime(initialIssue.created_at)}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Solved Date</dt>
                        <dd class="text-right font-medium text-[#071a2f]">
                            {formatDateTime(initialIssue.closed_at)}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="flex items-center gap-2 border-b border-slate-200 pb-3"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-[#eff5fb] text-[#002b57]"
                    >
                        <Link2 class="size-4" />
                    </span>
                    <h2
                        class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                    >
                        Linked Data
                    </h2>
                </div>

                <div class="mt-4 space-y-4">
                    <article>
                        <h3
                            class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            Linked Test Plan
                        </h3>
                        {#if initialIssue.test_plan.id}
                            <Link
                                href={testPlansShow(initialIssue.test_plan.id)}
                                class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-[#002b57] transition-colors hover:text-[#003c78] hover:underline"
                            >
                                {initialIssue.test_plan.name ?? 'Unnamed plan'}
                                <ArrowRight class="size-4" />
                            </Link>
                        {:else}
                            <p class="mt-2 text-sm text-slate-500">
                                Not linked
                            </p>
                        {/if}
                    </article>

                    <article>
                        <h3
                            class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            Linked Test Run
                        </h3>
                        {#if initialIssue.test_run.id}
                            <Link
                                href={testExecutionsShow(
                                    initialIssue.test_run.id,
                                )}
                                class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-[#002b57] transition-colors hover:text-[#003c78] hover:underline"
                            >
                                {initialIssue.test_run.name ?? 'Unnamed run'}
                                <ArrowRight class="size-4" />
                            </Link>
                            {#if initialIssue.test_run.status}
                                <p class="mt-1 text-sm text-slate-500">
                                    Status: {initialIssue.test_run.status}
                                </p>
                            {/if}
                        {:else}
                            <p class="mt-2 text-sm text-slate-500">
                                Not linked
                            </p>
                        {/if}
                    </article>
                </div>
            </section>
        </aside>
    </div>
</div>
