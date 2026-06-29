<script module lang="ts">
    import { index as reportsIndexRoute } from '@/routes/reports';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reports',
                href: reportsIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Activity from 'lucide-svelte/icons/activity';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import Bug from 'lucide-svelte/icons/bug';
    import Download from 'lucide-svelte/icons/download';
    import FileText from 'lucide-svelte/icons/file-text';
    import ListChecks from 'lucide-svelte/icons/list-checks';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
    import Plus from 'lucide-svelte/icons/plus';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import { exportReportPdf } from '@/lib/api/reports';
    import { index as reportsIndex } from '@/routes/reports';

    type ReportOption = {
        value: string;
        label: string;
    };

    type PlanOption = {
        id: number;
        name: string;
    };

    type ReportFilters = {
        scope: string;
        plan_id: number | null;
        range: string;
        start_date: string;
        end_date: string;
        grouping: 'daily' | 'weekly';
        sections: string[];
    };

    type ReportPayload = {
        metrics: {
            totalTestCases: number;
            totalTestRuns: number;
            totalIssues: number;
            executionCoverage: number;
        };
        trend: Array<{
            label: string;
            bucket: string;
            value: number;
        }>;
        distribution: Array<{
            label: string;
            value: number;
        }>;
        results: {
            total: number;
            statuses: Array<{
                label: string;
                key: string;
                value: number;
                percent: number;
            }>;
        };
        workload: Array<{
            name: string;
            initials: string;
            activeRuns: number;
            assignedCases: number;
        }>;
        recentExecutions: Array<{
            id: number;
            name: string;
            date: string | null;
            plan: {
                id: number;
                name: string | null;
            };
            result: string;
            tester: string;
            status: string;
            href: string;
        }>;
        filters: ReportFilters;
        options: {
            hasActiveProject: boolean;
            project: {
                id: number;
                name: string;
            } | null;
            testPlans: PlanOption[];
            ranges: ReportOption[];
            groupings: ReportOption[];
            sections: ReportOption[];
        };
    };

    let {
        initialPayload,
    }: {
        initialPayload: ReportPayload;
    } = $props();

    const getStartingPayload = (): ReportPayload => initialPayload;
    const startingPayload = getStartingPayload();

    let payload = $state(startingPayload);
    let customReportOpen = $state(false);
    let customFilters = $state<ReportFilters>({
        ...startingPayload.filters,
        sections: [...startingPayload.filters.sections],
    });
    let exportingPdf = $state(false);

    const cloneCurrentFilters = (): ReportFilters => ({
        ...payload.filters,
        sections: [...payload.filters.sections],
    });

    const toUrl = (routeValue: string | { url: string }): string =>
        typeof routeValue === 'string' ? routeValue : routeValue.url;

    const hasSection = (section: string): boolean =>
        payload.filters.sections.includes(section);

    const maxTrendValue = $derived(
        Math.max(1, ...payload.trend.map((item) => item.value)),
    );
    const distributionTotal = $derived(
        payload.distribution.reduce((total, item) => total + item.value, 0),
    );
    const maxWorkloadCases = $derived(
        Math.max(1, ...payload.workload.map((item) => item.assignedCases)),
    );
    const metricCards = $derived([
        {
            title: 'Total Test Cases',
            value: formatNumber(payload.metrics.totalTestCases),
            caption: scopeLabel(payload.filters.scope),
            icon: ListChecks,
            iconClass: 'bg-blue-50 text-blue-700',
            accent: '+12.5%',
            accentClass: 'bg-emerald-50 text-emerald-700',
        },
        {
            title: 'Total Test Runs',
            value: formatNumber(payload.metrics.totalTestRuns),
            caption: rangeLabel(payload.filters.range),
            icon: Activity,
            iconClass: 'bg-indigo-50 text-indigo-700',
            accent: payload.filters.grouping === 'daily' ? 'Daily' : 'Weekly',
            accentClass: 'bg-slate-100 text-slate-600',
        },
        {
            title: 'Total Issues',
            value: formatNumber(payload.metrics.totalIssues),
            caption: 'Open and historical',
            icon: Bug,
            iconClass: 'bg-red-50 text-red-700',
            accent: issueSeverityLabel(),
            accentClass: 'bg-red-50 text-red-700',
        },
        {
            title: 'Execution Coverage',
            value: `${payload.metrics.executionCoverage.toFixed(1)}%`,
            caption: 'Executed case coverage',
            icon: BarChart3,
            iconClass: 'bg-sky-50 text-sky-700',
            accent: '98% Target',
            accentClass: 'bg-blue-50 text-blue-700',
        },
    ]);

    function formatNumber(value: number): string {
        return new Intl.NumberFormat('en-US').format(value);
    }

    function rangeLabel(range: string): string {
        return (
            payload.options.ranges.find((option) => option.value === range)
                ?.label ?? 'Custom Range'
        );
    }

    function scopeLabel(scope: string): string {
        if (scope === 'all') {
            return 'All Plans';
        }

        return (
            payload.options.testPlans.find(
                (plan) => String(plan.id) === String(scope),
            )?.name ?? 'Selected Plan'
        );
    }

    function issueSeverityLabel(): string {
        const criticalCount =
            payload.distribution.find((item) => item.label === 'Critical')
                ?.value ?? 0;

        return criticalCount > 0 ? `${criticalCount} Critical` : 'Stable';
    }

    function resultClass(result: string): string {
        if (result === 'Pass') {
            return 'border-emerald-200 bg-emerald-50 text-emerald-700';
        }

        if (result === 'Fail') {
            return 'border-red-200 bg-red-50 text-red-700';
        }

        if (result === 'Blocked') {
            return 'border-amber-200 bg-amber-50 text-amber-800';
        }

        return 'border-slate-200 bg-slate-100 text-slate-700';
    }

    function resultColor(key: string): string {
        const colors: Record<string, string> = {
            passed: 'bg-emerald-500',
            failed: 'bg-red-600',
            in_testing: 'bg-sky-500',
            blocked: 'bg-amber-500',
            skipped: 'bg-slate-400',
            not_run: 'bg-slate-300',
        };

        return colors[key] ?? 'bg-slate-300';
    }

    function distributionColor(label: string): string {
        const colors: Record<string, string> = {
            Critical: '#dc2626',
            High: '#f43f5e',
            Medium: '#f59e0b',
            Low: '#64748b',
        };

        return colors[label] ?? '#94a3b8';
    }

    function buildSearchParams(filters: ReportFilters): URLSearchParams {
        const params = new URLSearchParams();
        params.set('scope', filters.scope);
        params.set('range', filters.range);
        params.set('grouping', filters.grouping);
        params.set('sections', filters.sections.join(','));

        if (filters.range === 'custom') {
            params.set('start_date', filters.start_date);
            params.set('end_date', filters.end_date);
        }

        return params;
    }

    function applyFilters(nextFilters: ReportFilters): void {
        router.get(
            toUrl(reportsIndex()),
            Object.fromEntries(buildSearchParams(nextFilters)),
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    }

    function openCustomReport(): void {
        customFilters = cloneCurrentFilters();
        customReportOpen = true;
    }

    function generateCustomReport(): void {
        customReportOpen = false;
        applyFilters(customFilters);
    }

    function toggleSection(section: string, enabled: boolean): void {
        customFilters.sections = enabled
            ? Array.from(new Set([...customFilters.sections, section]))
            : customFilters.sections.filter((item) => item !== section);
    }

    async function exportPdf(): Promise<void> {
        if (exportingPdf || !payload.options.hasActiveProject) {
            return;
        }

        exportingPdf = true;

        try {
            const download = await exportReportPdf(payload.filters);
            const downloadUrl = URL.createObjectURL(download.blob);
            const link = document.createElement('a');

            link.href = downloadUrl;
            link.download = download.filename ?? 'testify-ai-report.pdf';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 0);

            toast.success('AI report PDF generated.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to generate the AI report PDF.',
            );
        } finally {
            exportingPdf = false;
        }
    }
</script>

<AppHead title="Reports" />

<div class="px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="text-[11px] font-semibold uppercase text-slate-500"
                >
                    Analysis Scope
                </span>
                <select
                    id="reports-scope-filter"
                    aria-label="Select report analysis scope"
                    class="h-10 min-w-44 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-[#071a2f] shadow-sm outline-none transition focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    value={payload.filters.scope}
                    disabled={!payload.options.hasActiveProject}
                    onchange={(event) =>
                        applyFilters({
                            ...payload.filters,
                            scope: event.currentTarget.value,
                        })}
                >
                    <option value="all">All Plans</option>
                    {#each payload.options.testPlans as plan (plan.id)}
                        <option value={String(plan.id)}>{plan.name}</option>
                    {/each}
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 gap-2 border-slate-200 bg-white text-[#071a2f] hover:bg-slate-50"
                    disabled={!payload.options.hasActiveProject || exportingPdf}
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
                <Button
                    type="button"
                    class="h-10 gap-2 bg-[#0057d8] text-white hover:bg-[#004abb]"
                    disabled={!payload.options.hasActiveProject}
                    onclick={openCustomReport}
                >
                    <Plus class="size-4" />
                    Generate Custom Report
                </Button>
            </div>
        </section>

        {#if !payload.options.hasActiveProject}
            <section
                class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                >
                    <FileText class="size-5" />
                </div>
                <h1 class="mt-4 text-xl font-semibold text-[#071a2f]">
                    Select a project to view reports
                </h1>
                <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600">
                    Reports use the active QA workspace project. Open a project
                    workspace first, then come back here for analytics.
                </p>
            </section>
        {:else}
            {#if hasSection('metrics')}
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {#each metricCards as card (card.title)}
                        <article
                            class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class={`flex size-11 items-center justify-center rounded-lg ${card.iconClass}`}
                                >
                                    <card.icon class="size-5" />
                                </div>
                                <span
                                    class={`rounded px-2 py-1 text-[11px] font-semibold ${card.accentClass}`}
                                >
                                    {card.accent}
                                </span>
                            </div>
                            <p class="mt-4 text-sm text-slate-600">
                                {card.title}
                            </p>
                            <p class="mt-1 text-3xl font-bold text-[#071a2f]">
                                {card.value}
                            </p>
                            <p class="mt-1 truncate text-xs text-slate-500">
                                {card.caption}
                            </p>
                        </article>
                    {/each}
                </section>
            {/if}

            <section class="grid gap-4 lg:grid-cols-[2fr_1fr]">
                {#if hasSection('issue_trend')}
                    <article
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-[#071a2f]">
                                Issue Trend
                            </h2>
                            <div
                                class="flex rounded-full bg-slate-100 p-1 text-[11px] font-semibold"
                            >
                                <button
                                    id="reports-group-daily"
                                    type="button"
                                    class={`rounded-full px-3 py-1 ${payload.filters.grouping === 'daily' ? 'bg-blue-50 text-blue-700' : 'text-slate-600'}`}
                                    onclick={() =>
                                        applyFilters({
                                            ...payload.filters,
                                            grouping: 'daily',
                                        })}
                                >
                                    Daily
                                </button>
                                <button
                                    id="reports-group-weekly"
                                    type="button"
                                    class={`rounded-full px-3 py-1 ${payload.filters.grouping === 'weekly' ? 'bg-blue-50 text-blue-700' : 'text-slate-600'}`}
                                    onclick={() =>
                                        applyFilters({
                                            ...payload.filters,
                                            grouping: 'weekly',
                                        })}
                                >
                                    Weekly
                                </button>
                            </div>
                        </div>

                        <div class="mt-8 flex h-56 items-end gap-4 px-3">
                            {#each payload.trend as point (point.bucket)}
                                <div
                                    class="flex min-w-0 flex-1 flex-col items-center gap-2"
                                >
                                    <div
                                        class="w-full max-w-10 rounded-t-sm bg-blue-100 transition-colors hover:bg-[#0057d8]"
                                        style={`height: ${Math.max(12, (point.value / maxTrendValue) * 184)}px`}
                                        title={`${point.value} issues`}
                                    ></div>
                                    <span class="text-[11px] text-slate-500">
                                        {point.label}
                                    </span>
                                </div>
                            {/each}
                        </div>
                    </article>
                {/if}

                {#if hasSection('issue_distribution')}
                    <article
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Issue Distribution
                        </h2>
                        <div class="mt-6 flex flex-col items-center">
                            <div
                                class="grid size-36 place-items-center rounded-full"
                                style={`background: conic-gradient(${payload.distribution
                                    .map((item, index) => {
                                        const previous = payload.distribution
                                            .slice(0, index)
                                            .reduce(
                                                (total, value) =>
                                                    total + value.value,
                                                0,
                                            );
                                        const start =
                                            distributionTotal === 0
                                                ? 0
                                                : (previous /
                                                      distributionTotal) *
                                                  100;
                                        const end =
                                            distributionTotal === 0
                                                ? 0
                                                : ((previous + item.value) /
                                                      distributionTotal) *
                                                  100;

                                        return `${distributionColor(item.label)} ${start}% ${end}%`;
                                    })
                                    .join(', ')}, #e2e8f0 0 100%)`}
                            >
                                <div
                                    class="grid size-20 place-items-center rounded-full bg-white text-center shadow-inner"
                                >
                                    <span
                                        class="text-lg font-bold text-[#071a2f]"
                                    >
                                        {distributionTotal}
                                    </span>
                                    <span
                                        class="-mt-2 text-[11px] text-slate-500"
                                    >
                                        Total
                                    </span>
                                </div>
                            </div>
                            <div class="mt-6 grid w-full grid-cols-2 gap-3">
                                {#each payload.distribution as item (item.label)}
                                    <div
                                        class="flex items-center gap-2 text-xs text-slate-600"
                                    >
                                        <span
                                            class="size-2 rounded-full"
                                            style={`background-color: ${distributionColor(item.label)}`}
                                        ></span>
                                        <span>{item.label} ({item.value})</span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </article>
                {/if}
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                {#if hasSection('result_summary')}
                    <article
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-[#071a2f]">
                                Test Results Summary
                            </h2>
                            <span class="text-xs text-slate-500">
                                {rangeLabel(payload.filters.range)}
                            </span>
                        </div>
                        <div
                            class="mt-6 flex h-12 overflow-hidden rounded-md bg-slate-100"
                        >
                            {#each payload.results.statuses as status (status.key)}
                                {#if status.percent > 0}
                                    <div
                                        class={`grid min-w-8 place-items-center text-[11px] font-bold text-white ${resultColor(status.key)}`}
                                        style={`width: ${status.percent}%`}
                                    >
                                        {status.percent}%
                                    </div>
                                {/if}
                            {/each}
                            {#if payload.results.total === 0}
                                <div
                                    class="grid flex-1 place-items-center text-xs font-medium text-slate-500"
                                >
                                    No execution results
                                </div>
                            {/if}
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                            {#each payload.results.statuses as status (status.key)}
                                <div>
                                    <p
                                        class={`text-lg font-bold ${status.key === 'passed' ? 'text-emerald-600' : status.key === 'failed' ? 'text-red-600' : status.key === 'in_testing' ? 'text-sky-600' : status.key === 'blocked' ? 'text-amber-600' : 'text-slate-500'}`}
                                    >
                                        {formatNumber(status.value)}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {status.label}
                                    </p>
                                </div>
                            {/each}
                        </div>
                    </article>
                {/if}

                {#if hasSection('team_workload')}
                    <article
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Team Workload
                        </h2>
                        <div class="mt-5 space-y-4">
                            {#each payload.workload as member (member.name)}
                                <div class="grid grid-cols-[2.75rem_1fr] gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700"
                                    >
                                        {member.initials}
                                    </div>
                                    <div class="min-w-0">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <p
                                                class="truncate text-sm font-semibold text-[#071a2f]"
                                            >
                                                {member.name}
                                            </p>
                                            <span
                                                class="text-[11px] font-semibold text-slate-500"
                                            >
                                                {member.assignedCases} cases
                                            </span>
                                        </div>
                                        <div
                                            class="mt-2 h-2 rounded-full bg-slate-100"
                                        >
                                            <div
                                                class="h-2 rounded-full bg-[#0057d8]"
                                                style={`width: ${(member.assignedCases / maxWorkloadCases) * 100}%`}
                                            ></div>
                                        </div>
                                        <p
                                            class="mt-1 text-[11px] text-slate-500"
                                        >
                                            {member.activeRuns} active runs
                                        </p>
                                    </div>
                                </div>
                            {:else}
                                <p
                                    class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600"
                                >
                                    No active workload in this report range.
                                </p>
                            {/each}
                        </div>
                    </article>
                {/if}
            </section>

            {#if hasSection('recent_executions')}
                <section
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
                >
                    <div class="flex items-center justify-between px-5 py-4">
                        <h2 class="text-sm font-semibold text-[#071a2f]">
                            Recent Executions
                        </h2>
                        <Link
                            href="/test-executions"
                            class="text-xs font-semibold text-[#0057d8] hover:underline"
                        >
                            View All
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            id="reports-recent-executions-table"
                            aria-label="Recent test executions"
                            class="min-w-full divide-y divide-slate-200 text-sm"
                        >
                            <thead
                                class="bg-slate-50 text-[11px] uppercase text-slate-500"
                            >
                                <tr>
                                    <th
                                        class="px-5 py-3 text-left font-semibold"
                                    >
                                        Run Name
                                    </th>
                                    <th
                                        class="px-5 py-3 text-left font-semibold"
                                    >
                                        Date
                                    </th>
                                    <th
                                        class="px-5 py-3 text-left font-semibold"
                                    >
                                        Plan
                                    </th>
                                    <th
                                        class="px-5 py-3 text-left font-semibold"
                                    >
                                        Result
                                    </th>
                                    <th
                                        class="px-5 py-3 text-left font-semibold"
                                    >
                                        Tester
                                    </th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each payload.recentExecutions as execution (execution.id)}
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-4">
                                            <Link
                                                href={execution.href}
                                                class="font-semibold text-[#071a2f] hover:text-[#0057d8]"
                                            >
                                                {execution.name}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            {execution.date ?? '-'}
                                        </td>
                                        <td class="px-5 py-4">
                                            <span
                                                class="rounded bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700"
                                            >
                                                {execution.plan.name}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span
                                                class={`rounded-full border px-2 py-1 text-[11px] font-bold uppercase ${resultClass(execution.result)}`}
                                            >
                                                {execution.result}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            {execution.tester}
                                        </td>
                                        <td
                                            class="px-5 py-4 text-right text-slate-400"
                                        >
                                            <MoreVertical
                                                class="ml-auto size-4"
                                            />
                                        </td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td
                                            colspan="6"
                                            class="px-5 py-8 text-center text-sm text-slate-500"
                                        >
                                            No executions in this report range.
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </section>
            {/if}
        {/if}
    </div>
</div>

<Dialog bind:open={customReportOpen}>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-white text-[#071a2f] sm:max-w-2xl"
    >
        <DialogTitle>Generate Custom Report</DialogTitle>
        <DialogDescription>
            Choose the scope, date range, grouping, and report sections.
        </DialogDescription>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label for="custom-report-scope" class="space-y-2">
                <span class="text-sm font-semibold text-[#071a2f]">Scope</span>
                <select
                    id="custom-report-scope"
                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    bind:value={customFilters.scope}
                >
                    <option value="all">All Plans</option>
                    {#each payload.options.testPlans as plan (plan.id)}
                        <option value={String(plan.id)}>{plan.name}</option>
                    {/each}
                </select>
            </label>

            <label for="custom-report-range" class="space-y-2">
                <span class="text-sm font-semibold text-[#071a2f]">
                    Date Range
                </span>
                <select
                    id="custom-report-range"
                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    bind:value={customFilters.range}
                >
                    {#each payload.options.ranges as range (range.value)}
                        <option value={range.value}>{range.label}</option>
                    {/each}
                </select>
            </label>

            {#if customFilters.range === 'custom'}
                <label for="custom-report-start-date" class="space-y-2">
                    <span class="text-sm font-semibold text-[#071a2f]">
                        Start Date
                    </span>
                    <Input
                        id="custom-report-start-date"
                        type="date"
                        bind:value={customFilters.start_date}
                    />
                </label>

                <label for="custom-report-end-date" class="space-y-2">
                    <span class="text-sm font-semibold text-[#071a2f]">
                        End Date
                    </span>
                    <Input
                        id="custom-report-end-date"
                        type="date"
                        bind:value={customFilters.end_date}
                    />
                </label>
            {/if}

            <label for="custom-report-grouping" class="space-y-2">
                <span class="text-sm font-semibold text-[#071a2f]"
                    >Grouping</span
                >
                <select
                    id="custom-report-grouping"
                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    bind:value={customFilters.grouping}
                >
                    {#each payload.options.groupings as grouping (grouping.value)}
                        <option value={grouping.value}>{grouping.label}</option>
                    {/each}
                </select>
            </label>
        </div>

        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-[#071a2f]">Visible Sections</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                {#each payload.options.sections as section (section.value)}
                    <label
                        for={`custom-report-section-${section.value}`}
                        class="flex items-center gap-3 text-sm text-slate-700"
                    >
                        <input
                            id={`custom-report-section-${section.value}`}
                            type="checkbox"
                            class="size-4 rounded border-slate-300 text-[#0057d8] focus:ring-[#0057d8]"
                            checked={customFilters.sections.includes(
                                section.value,
                            )}
                            onchange={(event) =>
                                toggleSection(
                                    section.value,
                                    event.currentTarget.checked,
                                )}
                        />
                        <span>{section.label}</span>
                    </label>
                {/each}
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <Button
                type="button"
                variant="outline"
                onclick={() => (customReportOpen = false)}
            >
                Cancel
            </Button>
            <Button
                type="button"
                class="bg-[#0057d8] text-white hover:bg-[#004abb]"
                onclick={generateCustomReport}
            >
                Generate Report
            </Button>
        </div>
    </DialogContent>
</Dialog>
