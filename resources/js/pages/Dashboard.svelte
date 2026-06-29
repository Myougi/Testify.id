<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Beaker from 'lucide-svelte/icons/beaker';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import CircleDotDashed from 'lucide-svelte/icons/circle-dot-dashed';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import RefreshCcw from 'lucide-svelte/icons/refresh-ccw';
    import XCircle from 'lucide-svelte/icons/x-circle';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { index as issuesIndex } from '@/routes/issues';
    import { index as testExecutionsIndex } from '@/routes/test-executions';
    import { index as testPlansIndex } from '@/routes/test-plans';

    type DashboardMetricKey =
        | 'totalTestCases'
        | 'notRun'
        | 'inTesting'
        | 'passed'
        | 'failed';

    type DashboardPayload = {
        metrics: Record<DashboardMetricKey, number>;
        workload: Array<{
            name: string;
            initials: string;
            activeRuns: number;
            assignedCases: number;
        }>;
        recentIssues: Array<{
            id: number;
            issueKey: string;
            title: string;
            status: string;
            priority: string;
            severity: string;
            reporter: string;
            href: string;
        }>;
        overduePlans: Array<{
            id: number;
            name: string;
            assignedTo: string;
            assignedInitials: string;
            dueDate: string;
            progress: number;
            href: string;
        }>;
        options: {
            hasActiveProject: boolean;
            project: {
                id: number;
                name: string;
            } | null;
        };
    };

    let {
        initialPayload,
    }: {
        initialPayload: DashboardPayload;
    } = $props();

    const numberFormatter = new Intl.NumberFormat();
    const maxAssignedCases = $derived(
        Math.max(
            1,
            ...initialPayload.workload.map((member) => member.assignedCases),
        ),
    );
    const hasDashboardData = $derived(
        initialPayload.options.hasActiveProject &&
            (initialPayload.metrics.totalTestCases > 0 ||
                initialPayload.workload.length > 0 ||
                initialPayload.recentIssues.length > 0 ||
                initialPayload.overduePlans.length > 0),
    );

    const metricCards = $derived([
        {
            title: 'Total Test Case',
            value: initialPayload.metrics.totalTestCases,
            key: 'total',
            icon: ClipboardCheck,
            valueClass: 'text-[#002b57]',
            iconClass: 'text-[#002b57]',
        },
        {
            title: 'Not Run',
            value: initialPayload.metrics.notRun,
            key: 'not-run',
            icon: CircleDotDashed,
            valueClass: 'text-slate-700',
            iconClass: 'text-slate-600',
        },
        {
            title: 'In Testing',
            value: initialPayload.metrics.inTesting,
            key: 'in-testing',
            icon: Beaker,
            valueClass: 'text-[#002b57]',
            iconClass: 'text-[#002b57]',
        },
        {
            title: 'Passed',
            value: initialPayload.metrics.passed,
            key: 'passed',
            icon: CheckCircle2,
            valueClass: 'text-emerald-700',
            iconClass: 'text-emerald-700',
        },
        {
            title: 'Failed',
            value: initialPayload.metrics.failed,
            key: 'failed',
            icon: XCircle,
            valueClass: 'text-red-700',
            iconClass: 'text-red-700',
        },
    ]);

    const formatNumber = (value: number): string =>
        numberFormatter.format(value);

    const formatDate = (value: string): string => {
        if (!value) {
            return '-';
        }

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(new Date(`${value}T00:00:00`));
    };

    const statusClass = (status: string): string => {
        const classes: Record<string, string> = {
            Open: 'bg-rose-50 text-rose-700',
            'In Investigation': 'bg-slate-100 text-slate-700',
            'In Testing': 'bg-amber-50 text-amber-700',
            Solved: 'bg-emerald-50 text-emerald-700',
            Deferred: 'bg-slate-100 text-slate-600',
        };

        return classes[status] ?? 'bg-slate-100 text-slate-700';
    };

    const severityClass = (severity: string): string => {
        const classes: Record<string, string> = {
            Low: 'text-slate-600',
            Medium: 'text-amber-700',
            High: 'text-rose-700',
            Critical: 'text-red-700',
        };

        return classes[severity] ?? 'text-slate-600';
    };

    const progressClass = (progress: number): string => {
        if (progress >= 75) {
            return 'bg-emerald-600';
        }

        if (progress >= 40) {
            return 'bg-amber-500';
        }

        return 'bg-red-600';
    };
</script>

<AppHead title="Dashboard" />

<div class="px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
        <section
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                >
                    QA Workspace
                </p>
                <h1 class="mt-1 text-2xl font-bold text-[#071a2f]">
                    {initialPayload.options.project?.name ?? 'Dashboard'}
                </h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    href={toUrl(testExecutionsIndex())}
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-[#071a2f] shadow-sm transition hover:bg-slate-50"
                >
                    <RefreshCcw class="size-4" />
                    Reassign Workload
                </Link>
                <Link
                    href={toUrl(
                        testPlansIndex({ query: { status: 'In Progress' } }),
                    )}
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-red-700 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                >
                    <AlertTriangle class="size-4" />
                    Alert Team
                </Link>
            </div>
        </section>

        {#if !initialPayload.options.hasActiveProject}
            <section
                class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm"
            >
                <h2 class="text-lg font-semibold text-[#071a2f]">
                    Select a project to see your QA dashboard
                </h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600">
                    Dashboard metrics are scoped to the active workspace project
                    so test plans, executions, and issues stay separated.
                </p>
            </section>
        {:else}
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {#each metricCards as card (card.key)}
                    {@const Icon = card.icon}
                    <article
                        class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="text-[11px] font-bold uppercase text-[#071a2f]"
                            >
                                {card.title}
                            </p>
                            <Icon class={`size-4 ${card.iconClass}`} />
                        </div>
                        <p class={`mt-3 text-2xl font-bold ${card.valueClass}`}>
                            {formatNumber(card.value)}
                        </p>
                    </article>
                {/each}
            </section>

            {#if !hasDashboardData}
                <section
                    class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm"
                >
                    <h2 class="text-lg font-semibold text-[#071a2f]">
                        This project is ready for QA data
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600">
                        Add test cases, create test plans, run executions, or
                        log issues to populate the dashboard.
                    </p>
                </section>
            {/if}

            <section class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <article
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold text-[#071a2f]">
                            Team Workload
                        </h2>
                        <span class="text-[11px] font-semibold text-slate-500">
                            Active Test Runs
                        </span>
                    </div>

                    <div class="mt-5 space-y-5">
                        {#each initialPayload.workload as member (member.name)}
                            <div class="grid grid-cols-[2.25rem_1fr] gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-full bg-[#eef5ff] text-xs font-bold text-[#002b57]"
                                >
                                    {member.initials}
                                </div>
                                <div class="min-w-0">
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <p
                                            class="truncate text-sm font-bold text-[#071a2f]"
                                        >
                                            {member.name}
                                        </p>
                                        <span
                                            class="shrink-0 text-[11px] font-semibold text-slate-600"
                                        >
                                            {formatNumber(member.activeRuns)}
                                            {member.activeRuns === 1
                                                ? 'Run'
                                                : 'Runs'}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[#002b57]"
                                            style={`width: ${Math.max(8, (member.assignedCases / maxAssignedCases) * 100)}%`}
                                        ></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        {formatNumber(member.assignedCases)} cases
                                        assigned
                                    </p>
                                </div>
                            </div>
                        {:else}
                            <p
                                class="rounded-md bg-slate-50 p-4 text-sm text-slate-600"
                            >
                                No active test runs are assigned right now.
                            </p>
                        {/each}
                    </div>

                    <Link
                        href={toUrl(testExecutionsIndex())}
                        class="mt-6 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-slate-100 text-sm font-semibold text-[#071a2f] transition hover:bg-slate-200"
                    >
                        Reassign Workload
                    </Link>
                </article>

                <article
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold text-[#071a2f]">
                            Recent Issues
                        </h2>
                        <Link
                            href={toUrl(issuesIndex())}
                            class="inline-flex items-center gap-1 text-xs font-semibold text-[#0057d8] hover:underline"
                        >
                            View Bug Tracker
                            <ExternalLink class="size-3" />
                        </Link>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table
                            id="dashboard-recent-issues-table"
                            aria-label="Recent issues"
                            class="min-w-full text-sm"
                        >
                            <thead
                                class="border-b border-slate-200 text-[10px] uppercase text-slate-500"
                            >
                                <tr>
                                    <th class="py-3 pr-4 text-left font-bold">
                                        Issue
                                    </th>
                                    <th class="px-3 py-3 text-left font-bold">
                                        Status
                                    </th>
                                    <th class="px-3 py-3 text-left font-bold">
                                        Priority
                                    </th>
                                    <th class="py-3 pl-3 text-left font-bold">
                                        Reporter
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each initialPayload.recentIssues as issue (issue.id)}
                                    <tr>
                                        <td class="py-4 pr-4 align-top">
                                            <Link
                                                href={issue.href}
                                                class="font-bold text-[#071a2f] hover:text-[#0057d8]"
                                            >
                                                {issue.title}
                                            </Link>
                                            <p
                                                class="mt-1 text-[10px] text-slate-500"
                                            >
                                                {issue.issueKey}
                                            </p>
                                        </td>
                                        <td class="px-3 py-4 align-top">
                                            <span
                                                class={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${statusClass(issue.status)}`}
                                            >
                                                {issue.status}
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 align-top">
                                            <span
                                                class={`text-[11px] font-bold uppercase ${severityClass(issue.severity)}`}
                                            >
                                                {issue.priority}
                                            </span>
                                        </td>
                                        <td
                                            class="py-4 pl-3 align-top text-xs text-slate-600"
                                        >
                                            {issue.reporter}
                                        </td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td
                                            colspan="4"
                                            class="py-8 text-center text-sm text-slate-500"
                                        >
                                            No issues have been logged for this
                                            project.
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section>
                <div
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <h2 class="text-xl font-bold text-[#071a2f]">
                            Overdue Test Plans
                        </h2>
                        <p class="text-sm text-slate-600">
                            Plans requiring immediate attention
                        </p>
                    </div>
                    <Link
                        href={toUrl(
                            testPlansIndex({
                                query: { status: 'In Progress' },
                            }),
                        )}
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-red-700 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                    >
                        <AlertTriangle class="size-4" />
                        Alert Team
                    </Link>
                </div>

                <div
                    class="overflow-hidden rounded-lg border border-rose-100 bg-white shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table
                            id="dashboard-overdue-plans-table"
                            aria-label="Overdue test plans"
                            class="min-w-full text-sm"
                        >
                            <thead
                                class="bg-rose-50 text-[10px] uppercase text-red-700"
                            >
                                <tr>
                                    <th class="px-5 py-3 text-left font-bold">
                                        Plan Name
                                    </th>
                                    <th class="px-5 py-3 text-left font-bold">
                                        Assigned To
                                    </th>
                                    <th class="px-5 py-3 text-left font-bold">
                                        Due Date
                                    </th>
                                    <th class="px-5 py-3 text-left font-bold">
                                        Progress
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-rose-100">
                                {#each initialPayload.overduePlans as plan (plan.id)}
                                    <tr class="hover:bg-rose-50/40">
                                        <td class="px-5 py-4">
                                            <Link
                                                href={plan.href}
                                                class="font-bold text-[#071a2f] hover:text-[#0057d8]"
                                            >
                                                {plan.name}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="flex size-7 items-center justify-center rounded-full bg-[#eef5ff] text-[10px] font-bold text-[#002b57]"
                                                >
                                                    {plan.assignedInitials}
                                                </span>
                                                <span
                                                    class="text-sm text-slate-700"
                                                >
                                                    {plan.assignedTo}
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="px-5 py-4 font-semibold text-red-700"
                                        >
                                            {formatDate(plan.dueDate)}
                                        </td>
                                        <td class="px-5 py-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="h-2 w-28 overflow-hidden rounded-full bg-slate-100"
                                                >
                                                    <div
                                                        class={`h-full rounded-full ${progressClass(plan.progress)}`}
                                                        style={`width: ${plan.progress}%`}
                                                    ></div>
                                                </div>
                                                <span
                                                    class="text-xs font-semibold text-red-700"
                                                >
                                                    {plan.progress}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td
                                            colspan="4"
                                            class="px-5 py-8 text-center text-sm text-slate-500"
                                        >
                                            No overdue test plans for this
                                            project.
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        {/if}
    </div>
</div>
