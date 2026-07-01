<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import Bot from 'lucide-svelte/icons/bot';
    import Bug from 'lucide-svelte/icons/bug';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';
    import FolderKanban from 'lucide-svelte/icons/folder-kanban';
    import Menu from 'lucide-svelte/icons/menu';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import WandSparkles from 'lucide-svelte/icons/wand-sparkles';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import { toUrl } from '@/lib/utils';
    import { login } from '@/routes';
    import { index as projectsIndex } from '@/routes/projects';

    type PlanningTab = 'Plan' | 'Cases' | 'Assignments';
    type ReportScope = 'All Plans' | 'Release Window';
    type ReportCadence = 'Daily' | 'Weekly';
    type DraftState = 'idle' | 'generating' | 'ready';

    type PlanRow = {
        name: string;
        owner: string;
        status: string;
        dates: string;
    };

    type CaseRow = {
        title: string;
        module: string;
        priority: string;
        behavior: string;
    };

    type AssignmentRow = {
        tester: string;
        cases: string;
        runs: string;
        width: string;
    };

    type RunRow = {
        name: string;
        environment: string;
        priority: string;
        status: string;
        progress: string;
        width: string;
    };

    type IssueRow = {
        id: string;
        issue: string;
        priority: string;
        severity: string;
        status: string;
    };

    const auth = $derived(page.props.auth);
    const ctaLabel = $derived(auth.user ? 'Open workspace' : 'Log in');
    const ctaHref = $derived(auth.user ? projectsIndex() : login());

    let mobileMenuOpen = $state(false);
    let planningTab = $state<PlanningTab>('Plan');
    let reportScope = $state<ReportScope>('All Plans');
    let reportCadence = $state<ReportCadence>('Daily');
    let draftState = $state<DraftState>('idle');

    const navigationLinks = [
        { href: '#ai-planning', label: 'AI Planning' },
        { href: '#test-plans', label: 'Test Plans' },
        { href: '#reports', label: 'Reports' },
    ];

    const promptLines = [
        '# Feature',
        'Login and Change Password',
        '',
        '# User Story',
        'US-01: Sebagai Pengguna, Saya ingin masuk menggunakan email dan kata sandi, sehingga saya dapat mengakses fitur pemesanan obat medis saya secara aman.',
    ];

    const heroMetrics = [
        { label: 'Project', value: 'Spider Healthcare' },
        { label: 'Plans', value: '3 active drafts' },
        { label: 'Coverage', value: '26 repository cases' },
    ];

    const planRows: PlanRow[] = [
        {
            name: 'Authentication and Security - Login & Password Management',
            owner: 'Jody Maulana',
            status: 'Draft',
            dates: '2026-07-06 to 2026-07-10',
        },
        {
            name: 'Shopping Cart and Transaction Management',
            owner: 'Toto Suhariyanto',
            status: 'Draft',
            dates: '2026-07-13 to 2026-07-17',
        },
        {
            name: 'Delivery Information Management',
            owner: 'Abdul Aziz',
            status: 'Draft',
            dates: '2026-07-20 to 2026-07-24',
        },
    ];

    const caseRows: CaseRow[] = [
        {
            title: 'Verify delivery cost updates when address changes',
            module: 'Delivery',
            priority: 'Medium',
            behavior: 'Positive Functional',
        },
        {
            title: 'Verify successful Delivery Form submission with all valid data',
            module: 'Delivery',
            priority: 'High',
            behavior: 'Positive Functional',
        },
        {
            title: 'Verify mandatory field validation on Delivery Form',
            module: 'Delivery',
            priority: 'High',
            behavior: 'Negative Functional',
        },
    ];

    const assignmentRows: AssignmentRow[] = [
        {
            tester: 'Jody Maulana',
            cases: '11 cases assigned',
            runs: '1 active run',
            width: '100%',
        },
        {
            tester: 'Toto Suhariyanto',
            cases: '8 cases assigned',
            runs: '1 active run',
            width: '73%',
        },
        {
            tester: 'Abdul Aziz',
            cases: '7 cases assigned',
            runs: '1 active run',
            width: '64%',
        },
    ];

    const runRows: RunRow[] = [
        {
            name: 'Test Run 2026/07/01/001 - Delivery Form',
            environment: 'Development',
            priority: 'Medium',
            status: 'New',
            progress: '0/7',
            width: '0%',
        },
        {
            name: 'Test Run 2026/07/01/001 - Shopping Cart',
            environment: 'Development',
            priority: 'Medium',
            status: 'New',
            progress: '0/8',
            width: '0%',
        },
        {
            name: 'Test Run 2026/07/01/001 - Login & Change Password',
            environment: 'Development',
            priority: 'Medium',
            status: 'New',
            progress: '0/11',
            width: '0%',
        },
    ];

    const issueRows: IssueRow[] = [
        {
            id: 'ISS-001',
            issue: 'Inactive Kode ID allows successful login to the application',
            priority: 'High',
            severity: 'Critical',
            status: 'Open',
        },
    ];

    const reportTrend = [
        { label: 'Thu', height: '16%' },
        { label: 'Fri', height: '16%' },
        { label: 'Sat', height: '16%' },
        { label: 'Sun', height: '16%' },
        { label: 'Mon', height: '16%' },
        { label: 'Tue', height: '16%' },
        { label: 'Wed', height: '88%' },
    ];

    const closeMobileMenu = (): void => {
        mobileMenuOpen = false;
    };

    const startDraftGeneration = (): void => {
        if (draftState === 'generating') {
            return;
        }

        draftState = 'generating';

        window.setTimeout(() => {
            draftState = 'ready';
        }, 1200);
    };
</script>

<AppHead title="AI planning for release-ready QA">
    <meta
        name="description"
        content="Turn release scope into AI-generated test plans, coordinate runs, track issues, and prove readiness with reporting in Testify."
    />
</AppHead>

<div
    class="min-h-screen overflow-x-hidden bg-white text-[#071a2f] dark:bg-[#04101e] dark:text-slate-50"
>
    <header
        class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/94 backdrop-blur-xl dark:border-white/10 dark:bg-[#04101e]/92"
    >
        <div
            class="mx-auto flex h-[76px] w-full max-w-[1440px] items-center px-5 sm:px-8 lg:px-12"
        >
            <a
                href="#top"
                class="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5fff] focus-visible:ring-offset-4 dark:focus-visible:ring-offset-[#04101e]"
                aria-label="Testify home"
            >
                <AppLogo iconClass="size-8" wordmarkClass="text-[1.7rem]" />
            </a>

            <nav
                class="ml-14 hidden items-center gap-8 text-sm font-medium text-slate-600 lg:flex dark:text-slate-300"
                aria-label="Main navigation"
            >
                {#each navigationLinks as link (link.href)}
                    <a class="marketing-link" href={link.href}>{link.label}</a>
                {/each}
            </nav>

            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                <Link
                    href={toUrl(ctaHref)}
                    class="inline-flex h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-[#0b5fff] transition-colors hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5fff] sm:px-4 lg:h-11 lg:bg-[#0b5fff] lg:px-6 lg:text-white lg:shadow-[0_10px_24px_rgba(11,95,255,0.2)] lg:hover:bg-[#084ed7] dark:hover:bg-white/10 lg:dark:hover:bg-[#2d73ff]"
                >
                    {ctaLabel}
                </Link>
                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-lg text-[#071a2f] hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5fff] lg:hidden dark:text-white dark:hover:bg-white/10"
                    aria-label={mobileMenuOpen
                        ? 'Close navigation menu'
                        : 'Open navigation menu'}
                    aria-expanded={mobileMenuOpen}
                    aria-controls="mobile-navigation"
                    onclick={() => (mobileMenuOpen = !mobileMenuOpen)}
                >
                    {#if mobileMenuOpen}
                        <X class="size-6" />
                    {:else}
                        <Menu class="size-6" />
                    {/if}
                </button>
            </div>
        </div>

        {#if mobileMenuOpen}
            <nav
                id="mobile-navigation"
                class="border-t border-slate-200 bg-white px-5 py-4 lg:hidden dark:border-white/10 dark:bg-[#091627]"
                aria-label="Mobile navigation"
            >
                <div class="mx-auto grid max-w-[1440px] gap-1">
                    {#each navigationLinks as link (link.href)}
                        <a
                            class="mobile-nav-link"
                            href={link.href}
                            onclick={closeMobileMenu}>{link.label}</a
                        >
                    {/each}
                </div>
            </nav>
        {/if}
    </header>

    <main id="top">
        <section
            id="ai-planning"
            class="mx-auto grid w-full max-w-[1440px] gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.72fr_1.28fr] lg:gap-14 lg:px-12 lg:py-24"
            aria-labelledby="hero-title"
        >
            <div class="min-w-0 self-center">
                <h1
                    id="hero-title"
                    class="max-w-[640px] text-[2.7rem] leading-[1.01] font-semibold tracking-[-0.055em] text-balance sm:text-[clamp(3.2rem,5vw,5.35rem)]"
                >
                    Turn requirements into release-ready test plans.
                </h1>
                <p
                    class="mt-7 max-w-[620px] text-lg leading-8 text-slate-600 dark:text-slate-300"
                >
                    Plan clearly, generate with AI, coordinate every run, and
                    prove quality with reports your team can trust. Testify
                    brings release scope, reusable cases, issues, and reporting
                    into one focused QA workspace.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <Link href={toUrl(ctaHref)} class="primary-marketing-button"
                        >{ctaLabel}</Link
                    >
                    <a href="#test-plans" class="secondary-marketing-button">
                        See the workflow
                        <ArrowRight class="size-4" />
                    </a>
                </div>

                <dl
                    class="mt-10 grid gap-3 sm:grid-cols-3"
                    aria-label="Workspace overview"
                >
                    {#each heroMetrics as metric (metric.label)}
                        <div
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-[0_10px_30px_rgba(7,26,47,0.05)] dark:border-white/10 dark:bg-[#091627]"
                        >
                            <dt
                                class="text-[0.68rem] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {metric.label}
                            </dt>
                            <dd class="mt-2 text-base font-semibold">
                                {metric.value}
                            </dd>
                        </div>
                    {/each}
                </dl>
            </div>

            <div class="min-w-0">
                <div
                    class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(7,26,47,0.12)] dark:border-white/10 dark:bg-[#071525]"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <div>
                            <p
                                class="text-[0.7rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                AI Planning Workspace
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Generate Test Plan Draft
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#0b5fff] px-4 py-2 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(11,95,255,0.2)] transition-colors hover:bg-[#084ed7] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5fff] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#071525]"
                            onclick={startDraftGeneration}
                        >
                            <WandSparkles class="size-4" />
                            {draftState === 'generating'
                                ? 'Generating...'
                                : 'Generate Draft'}
                        </button>
                    </div>

                    <div class="grid gap-4 p-5 lg:grid-cols-[0.92fr_1.08fr]">
                        <div class="space-y-4">
                            <div
                                class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-[#0b1c30]"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Sparkles class="size-4 text-[#0b5fff]" />
                                    Prompt
                                </div>
                                <div
                                    class="mt-3 rounded-xl border border-slate-200 bg-white p-4 text-sm leading-6 whitespace-pre-line text-slate-700 dark:border-white/10 dark:bg-[#071525] dark:text-slate-200"
                                >
                                    {promptLines.join('\n')}
                                </div>
                                <div
                                    class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <span
                                        class="rounded-full bg-slate-200 px-2.5 py-1 font-medium dark:bg-white/10"
                                    >
                                        Project: Spider Healthcare
                                    </span>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-[#f1d38a] bg-[#fff8e6] p-4 text-sm text-[#9a5a00] dark:border-[#76541d] dark:bg-[#2a220f] dark:text-[#f0d699]"
                            >
                                <div
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <TriangleAlert class="size-4" />
                                    Review notes
                                </div>
                                <p class="mt-2 leading-6">
                                    Assumed standard HTTP 401 for auth failures
                                    and 429 for lockout. Assumed symbol
                                    requirements follow standard ASCII special
                                    characters.
                                </p>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#091627]"
                            aria-live="polite"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p
                                        class="text-[0.68rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Draft Name
                                    </p>
                                    <h3
                                        class="mt-2 text-xl font-semibold leading-tight"
                                    >
                                        Authentication and Security - Login &
                                        Password Management
                                    </h3>
                                </div>
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#0b5fff] dark:bg-[#12345d]"
                                >
                                    <Bot class="size-5" />
                                </span>
                            </div>

                            {#if draftState === 'generating'}
                                <div class="mt-6 space-y-3">
                                    <div
                                        class="skeleton h-5 w-32 rounded-full"
                                    ></div>
                                    <div
                                        class="skeleton h-20 rounded-2xl"
                                    ></div>
                                    <div
                                        class="skeleton h-16 rounded-2xl"
                                    ></div>
                                    <div
                                        class="skeleton h-20 rounded-2xl"
                                    ></div>
                                </div>
                            {:else}
                                <div
                                    class="mt-6 grid gap-5 text-sm leading-6 text-slate-700 dark:text-slate-200"
                                >
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p class="label-heading">
                                                Priority
                                            </p>
                                            <p class="mt-1 font-medium">High</p>
                                        </div>
                                        <div>
                                            <p class="label-heading">Tester</p>
                                            <p class="mt-1 font-medium">
                                                Senior QA Engineer (Testify
                                                Specialist Aplikasi Spider
                                                Healthcare)
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="label-heading">Description</p>
                                        <p class="mt-1">
                                            Comprehensive validation of US-01
                                            (Secure Login) and US-02 (Password
                                            Change) including security
                                            constraints and session management.
                                        </p>
                                    </div>
                                    <div>
                                        <p class="label-heading">Scope</p>
                                        <p class="mt-1">
                                            Validation of email/password
                                            authentication, account lockout
                                            mechanism, password complexity
                                            enforcement, and cross-device
                                            session invalidation.
                                        </p>
                                    </div>
                                    <div>
                                        <p class="label-heading">Criteria</p>
                                        <p class="mt-1">
                                            Entry: User stories signed off, API
                                            endpoints deployed to staging, test
                                            accounts created. Exit: 100% of
                                            High/Urgent test cases passed, zero
                                            P1/P2 bugs open, lockout timer
                                            verified.
                                        </p>
                                    </div>
                                </div>
                            {/if}

                            <div class="mt-5 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5fff] dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5"
                                    onclick={() => (draftState = 'ready')}
                                >
                                    Apply To Form
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="test-plans"
            class="border-y border-slate-200/80 bg-[#f8fbff] px-5 py-16 sm:px-8 sm:py-20 lg:px-12 dark:border-white/10 dark:bg-[#071525]"
            aria-labelledby="planning-title"
        >
            <div class="mx-auto max-w-[1440px]">
                <div
                    class="mb-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="max-w-[760px]">
                        <h2
                            id="planning-title"
                            class="text-[clamp(2.4rem,4vw,4.4rem)] leading-[1.03] font-semibold tracking-[-0.05em]"
                        >
                            Plan work in the same language your QA team already
                            uses.
                        </h2>
                        <p
                            class="mt-4 text-lg leading-8 text-slate-600 dark:text-slate-300"
                        >
                            Move from AI draft to owned plan, linked repository
                            cases, and clear tester assignments without leaving
                            the workspace.
                        </p>
                    </div>

                    <div
                        class="inline-flex w-full rounded-2xl border border-slate-200 bg-white p-1 lg:w-auto dark:border-white/10 dark:bg-[#091627]"
                        role="tablist"
                        aria-label="Planning views"
                    >
                        {#each ['Plan', 'Cases', 'Assignments'] as tab (tab)}
                            <button
                                type="button"
                                role="tab"
                                aria-selected={planningTab === tab}
                                class:active-tab={planningTab === tab}
                                class="planning-tab"
                                onclick={() =>
                                    (planningTab = tab as PlanningTab)}
                            >
                                {tab}
                            </button>
                        {/each}
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                    <article
                        class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(7,26,47,0.08)] dark:border-white/10 dark:bg-[#091627]"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="panel-kicker">Projects</p>
                                <h3 class="mt-1 text-2xl font-semibold">
                                    Active Projects
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-[#0d386b] px-4 py-2 text-sm font-semibold text-white"
                            >
                                + Create New Project
                            </button>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-4">
                            <div class="summary-card">
                                <span>Total Project</span>
                                <strong>1</strong>
                                <small>All projects in the workspace</small>
                            </div>
                            <div class="summary-card">
                                <span>Active</span>
                                <strong>1</strong>
                                <small
                                    class="text-emerald-600 dark:text-emerald-400"
                                >
                                    Status: Active
                                </small>
                            </div>
                            <div class="summary-card">
                                <span>In Progress</span>
                                <strong>0</strong>
                                <small>Status: In Progress</small>
                            </div>
                            <div class="summary-card">
                                <span>Archived</span>
                                <strong>0</strong>
                                <small>Status: Archived</small>
                            </div>
                        </div>

                        <div
                            class="mt-6 overflow-hidden rounded-[24px] border border-slate-200 dark:border-white/10"
                        >
                            <div
                                class="grid min-h-[220px] bg-[linear-gradient(180deg,#0b325c_0%,#08284a_100%)] p-6 text-white"
                            >
                                <div class="grid h-full content-between gap-10">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-white/14"
                                        >
                                            <FolderKanban class="size-5" />
                                        </span>
                                        <div>
                                            <h4 class="text-2xl font-semibold">
                                                Spider Healthcare
                                            </h4>
                                            <p
                                                class="mt-1 text-sm text-blue-100"
                                            >
                                                Dexa group technical test
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid gap-6 sm:grid-cols-2">
                                        <div>
                                            <p class="text-sm text-blue-100">
                                                Test Cases
                                            </p>
                                            <p
                                                class="mt-1 text-3xl font-semibold"
                                            >
                                                26
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-blue-100">
                                                Active Plans
                                            </p>
                                            <p
                                                class="mt-1 text-3xl font-semibold"
                                            >
                                                3
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-5 dark:bg-[#091627]">
                                <div
                                    class="flex items-center justify-between gap-4"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            Recent Execution Progress
                                        </p>
                                        <div
                                            class="mt-3 h-3 rounded-full bg-slate-100 dark:bg-white/10"
                                        >
                                            <div
                                                class="h-full w-[38%] rounded-full bg-[#0b5fff]"
                                            ></div>
                                        </div>
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-red-500"
                                    >
                                        1 issue
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article
                        class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(7,26,47,0.08)] dark:border-white/10 dark:bg-[#091627]"
                    >
                        {#if planningTab === 'Plan'}
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="panel-kicker">Test Plans</p>
                                    <h3 class="mt-1 text-2xl font-semibold">
                                        Release strategy stays readable.
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-[#0d386b] px-4 py-2 text-sm font-semibold text-white"
                                >
                                    + New Test Plan
                                </button>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-4">
                                <div class="summary-card">
                                    <span>Total Plans</span>
                                    <strong>3</strong>
                                    <small>Verification strategies</small>
                                </div>
                                <div class="summary-card">
                                    <span>Active</span>
                                    <strong>0</strong>
                                    <small>Status: Draft</small>
                                </div>
                                <div class="summary-card">
                                    <span>Completed</span>
                                    <strong>0</strong>
                                    <small>Awaiting execution</small>
                                </div>
                                <div class="summary-card">
                                    <span>Overdue</span>
                                    <strong>0</strong>
                                    <small>None this week</small>
                                </div>
                            </div>

                            <div
                                class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"
                            >
                                <div class="workspace-table-header">
                                    <span>Plan Name</span>
                                    <span>Owner</span>
                                    <span>Status</span>
                                    <span>Dates</span>
                                </div>
                                {#each planRows as plan (plan.name)}
                                    <div class="workspace-table-row">
                                        <div>
                                            <p class="font-semibold leading-6">
                                                {plan.name}
                                            </p>
                                        </div>
                                        <div
                                            class="text-slate-600 dark:text-slate-300"
                                        >
                                            {plan.owner}
                                        </div>
                                        <div>
                                            <span
                                                class="status-pill status-pill-blue"
                                            >
                                                {plan.status}
                                            </span>
                                        </div>
                                        <div
                                            class="text-slate-600 dark:text-slate-300"
                                        >
                                            {plan.dates}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {:else if planningTab === 'Cases'}
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="panel-kicker">Test Repository</p>
                                    <h3 class="mt-1 text-2xl font-semibold">
                                        Reusable cases stay linked to scope.
                                    </h3>
                                </div>
                                <div class="flex gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-white/15 dark:text-slate-200"
                                    >
                                        Generate Cases
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg bg-[#0d386b] px-4 py-2 text-sm font-semibold text-white"
                                    >
                                        + Add Test Case
                                    </button>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-5">
                                <div class="summary-card">
                                    <span>Total</span>
                                    <strong>26</strong>
                                    <small>Cases in scope</small>
                                </div>
                                <div class="summary-card">
                                    <span>Automated</span>
                                    <strong>0</strong>
                                    <small>Manual-first repo</small>
                                </div>
                                <div class="summary-card">
                                    <span>Manual</span>
                                    <strong>26</strong>
                                    <small>Current count</small>
                                </div>
                                <div class="summary-card">
                                    <span>Positive</span>
                                    <strong>10</strong>
                                    <small>Functional path</small>
                                </div>
                                <div class="summary-card">
                                    <span>Negative</span>
                                    <strong>16</strong>
                                    <small>Failure handling</small>
                                </div>
                            </div>

                            <div
                                class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"
                            >
                                <div
                                    class="workspace-table-header workspace-table-header-cases"
                                >
                                    <span>Title</span>
                                    <span>Module</span>
                                    <span>Priority</span>
                                    <span>Behavior / Type</span>
                                </div>
                                {#each caseRows as row (row.title)}
                                    <div
                                        class="workspace-table-row workspace-table-row-cases"
                                    >
                                        <div>
                                            <p class="font-semibold leading-6">
                                                {row.title}
                                            </p>
                                        </div>
                                        <div
                                            class="text-slate-600 dark:text-slate-300"
                                        >
                                            {row.module}
                                        </div>
                                        <div>
                                            <span
                                                class={row.priority === 'High'
                                                    ? 'status-pill status-pill-red'
                                                    : 'status-pill status-pill-amber'}
                                            >
                                                {row.priority}
                                            </span>
                                        </div>
                                        <div
                                            class="text-slate-600 dark:text-slate-300"
                                        >
                                            {row.behavior}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {:else}
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="panel-kicker">Assignments</p>
                                    <h3 class="mt-1 text-2xl font-semibold">
                                        Workload is visible before execution
                                        starts.
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-white/15 dark:text-slate-200"
                                >
                                    Reassign Workload
                                </button>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-4">
                                <div class="summary-card">
                                    <span>Total Test Case</span>
                                    <strong>26</strong>
                                    <small>All cases assigned</small>
                                </div>
                                <div class="summary-card">
                                    <span>Not Run</span>
                                    <strong>26</strong>
                                    <small>Waiting on starts</small>
                                </div>
                                <div class="summary-card">
                                    <span>Passed</span>
                                    <strong>0</strong>
                                    <small>Nothing closed yet</small>
                                </div>
                                <div class="summary-card">
                                    <span>Failed</span>
                                    <strong>0</strong>
                                    <small>Clean slate</small>
                                </div>
                            </div>

                            <div
                                class="mt-6 rounded-2xl border border-slate-200 p-5 dark:border-white/10"
                            >
                                {#each assignmentRows as row (row.tester)}
                                    <div class="assignment-row">
                                        <div class="flex items-center gap-3">
                                            <span class="assignment-avatar">
                                                {row.tester
                                                    .split(' ')
                                                    .map((part) => part[0])
                                                    .join('')
                                                    .slice(0, 2)}
                                            </span>
                                            <div>
                                                <p class="font-semibold">
                                                    {row.tester}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {row.cases}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <div
                                                class="flex items-center justify-between gap-3 text-sm"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                >
                                                    Active Test Runs
                                                </span>
                                                <span class="font-medium">
                                                    {row.runs}
                                                </span>
                                            </div>
                                            <div
                                                class="mt-3 h-3 rounded-full bg-slate-100 dark:bg-white/10"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-[#0d386b]"
                                                    style={`width: ${row.width}`}
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </article>
                </div>
            </div>
        </section>

        <section
            class="px-5 py-16 sm:px-8 sm:py-20 lg:px-12"
            aria-labelledby="execution-title"
        >
            <div class="mx-auto max-w-[1440px]">
                <div class="max-w-[760px]">
                    <h2
                        id="execution-title"
                        class="text-[clamp(2.4rem,4vw,4.4rem)] leading-[1.03] font-semibold tracking-[-0.05em]"
                    >
                        Coordinate runs, surface risk, and keep the team
                        aligned.
                    </h2>
                    <p
                        class="mt-4 text-lg leading-8 text-slate-600 dark:text-slate-300"
                    >
                        Recent runs, issue severity, and tester workload belong
                        in the same release conversation. Testify makes the next
                        action obvious before a defect becomes release noise.
                    </p>
                </div>

                <div class="mt-10 grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
                    <article
                        class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(7,26,47,0.08)] dark:border-white/10 dark:bg-[#091627]"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="panel-kicker">Recent Test Runs</p>
                                <h3 class="mt-1 text-2xl font-semibold">
                                    Operational visibility without extra
                                    dashboards.
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-[#0d386b] px-4 py-2 text-sm font-semibold text-white"
                            >
                                + Create Test Run
                            </button>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-4">
                            <div class="summary-card">
                                <span>Total Runs</span>
                                <strong>3</strong>
                                <small>Last 7 days</small>
                            </div>
                            <div class="summary-card">
                                <span>New</span>
                                <strong>3</strong>
                                <small>Awaiting start</small>
                            </div>
                            <div class="summary-card">
                                <span>Active</span>
                                <strong>0</strong>
                                <small>No live run now</small>
                            </div>
                            <div class="summary-card">
                                <span>Completed</span>
                                <strong>0</strong>
                                <small>Execution pending</small>
                            </div>
                        </div>

                        <div
                            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"
                        >
                            <div
                                class="workspace-table-header workspace-table-header-runs"
                            >
                                <span>Run Name</span>
                                <span>Environment</span>
                                <span>Priority</span>
                                <span>Status</span>
                                <span>Progress</span>
                            </div>
                            {#each runRows as run (run.name)}
                                <div
                                    class="workspace-table-row workspace-table-row-runs"
                                >
                                    <div>
                                        <p class="font-semibold leading-6">
                                            {run.name}
                                        </p>
                                    </div>
                                    <div>
                                        <span
                                            class="status-pill status-pill-cyan"
                                        >
                                            {run.environment}
                                        </span>
                                    </div>
                                    <div>
                                        <span
                                            class="status-pill status-pill-amber"
                                        >
                                            {run.priority}
                                        </span>
                                    </div>
                                    <div>
                                        <span
                                            class="status-pill status-pill-slate"
                                        >
                                            {run.status}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">
                                            {run.progress}
                                        </div>
                                        <div
                                            class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-white/10"
                                        >
                                            <div
                                                class="h-full rounded-full bg-[#0b5fff]"
                                                style={`width: ${run.width}`}
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </article>

                    <div class="grid gap-6">
                        <article
                            class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(7,26,47,0.08)] dark:border-white/10 dark:bg-[#091627]"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="panel-kicker">
                                        Issues & Bug Tracking
                                    </p>
                                    <h3 class="mt-1 text-2xl font-semibold">
                                        Failures stay linked to release risk.
                                    </h3>
                                </div>
                                <span
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-500 dark:bg-red-500/10"
                                >
                                    <Bug class="size-5" />
                                </span>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <div class="summary-card">
                                    <span>Total Active Issues</span>
                                    <strong>1</strong>
                                    <small>Open workload in project</small>
                                </div>
                                <div class="summary-card">
                                    <span>Critical Severity</span>
                                    <strong>1</strong>
                                    <small>Highest-risk defect</small>
                                </div>
                                <div class="summary-card">
                                    <span>Resolution Velocity</span>
                                    <strong>--</strong>
                                    <small>No resolved issues yet</small>
                                </div>
                            </div>

                            <div
                                class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"
                            >
                                <div
                                    class="workspace-table-header workspace-table-header-issues"
                                >
                                    <span>ID</span>
                                    <span>Issue</span>
                                    <span>Priority</span>
                                    <span>Severity</span>
                                    <span>Status</span>
                                </div>
                                {#each issueRows as issue (issue.id)}
                                    <div
                                        class="workspace-table-row workspace-table-row-issues"
                                    >
                                        <div class="font-semibold">
                                            {issue.id}
                                        </div>
                                        <div>
                                            <p class="font-semibold leading-6">
                                                {issue.issue}
                                            </p>
                                        </div>
                                        <div>
                                            <span
                                                class="status-pill status-pill-red"
                                            >
                                                {issue.priority}
                                            </span>
                                        </div>
                                        <div>
                                            <span
                                                class="status-pill status-pill-critical"
                                            >
                                                {issue.severity}
                                            </span>
                                        </div>
                                        <div>
                                            <span
                                                class="status-pill status-pill-cyan"
                                            >
                                                {issue.status}
                                            </span>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </article>

                        <article
                            class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(7,26,47,0.08)] dark:border-white/10 dark:bg-[#091627]"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="panel-kicker">Team Workload</p>
                                    <h3 class="mt-1 text-2xl font-semibold">
                                        Assignments stay visible across the
                                        release window.
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-white/15 dark:text-slate-200"
                                >
                                    Reassign Workload
                                </button>
                            </div>

                            <div class="mt-6 space-y-5">
                                {#each assignmentRows as row (row.tester)}
                                    <div>
                                        <div
                                            class="flex items-center justify-between gap-4"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span class="assignment-avatar">
                                                    {row.tester
                                                        .split(' ')
                                                        .map((part) => part[0])
                                                        .join('')
                                                        .slice(0, 2)}
                                                </span>
                                                <div>
                                                    <p class="font-semibold">
                                                        {row.tester}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                                    >
                                                        {row.runs}
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium">
                                                {row.cases}
                                            </span>
                                        </div>
                                        <div
                                            class="mt-3 h-3 rounded-full bg-slate-100 dark:bg-white/10"
                                        >
                                            <div
                                                class="h-full rounded-full bg-[#0b5fff]"
                                                style={`width: ${row.width}`}
                                            ></div>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="reports"
            class="bg-[#05284b] px-5 py-16 text-white sm:px-8 sm:py-20 lg:px-12"
            aria-labelledby="reports-title"
        >
            <div class="mx-auto max-w-[1440px]">
                <div
                    class="mb-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="max-w-[760px]">
                        <h2
                            id="reports-title"
                            class="text-[clamp(2.4rem,4vw,4.4rem)] leading-[1.03] font-semibold tracking-[-0.05em]"
                        >
                            Reporting turns daily execution into release
                            evidence.
                        </h2>
                        <p class="mt-4 text-lg leading-8 text-slate-300">
                            Show coverage, issue distribution, trend, and team
                            workload in one analysis surface. When the release
                            question comes, the answer is already organized.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div
                            class="inline-flex rounded-2xl border border-white/15 bg-white/5 p-1"
                            role="tablist"
                            aria-label="Report scope"
                        >
                            {#each ['All Plans', 'Release Window'] as scope (scope)}
                                <button
                                    type="button"
                                    class:report-toggle-active={reportScope ===
                                        scope}
                                    class="report-toggle"
                                    onclick={() =>
                                        (reportScope = scope as ReportScope)}
                                >
                                    {scope}
                                </button>
                            {/each}
                        </div>

                        <div
                            class="inline-flex rounded-2xl border border-white/15 bg-white/5 p-1"
                            role="tablist"
                            aria-label="Report cadence"
                        >
                            {#each ['Daily', 'Weekly'] as cadence (cadence)}
                                <button
                                    type="button"
                                    class:report-toggle-active={reportCadence ===
                                        cadence}
                                    class="report-toggle"
                                    onclick={() =>
                                        (reportCadence =
                                            cadence as ReportCadence)}
                                >
                                    {cadence}
                                </button>
                            {/each}
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="report-card">
                        <div
                            class="report-card-icon bg-white/10 text-[#69a9ff]"
                        >
                            <ClipboardList class="size-5" />
                        </div>
                        <p class="report-card-label">Total Test Cases</p>
                        <p class="mt-3 text-5xl font-semibold">26</p>
                        <p class="mt-3 text-sm text-slate-300">{reportScope}</p>
                    </div>
                    <div class="report-card">
                        <div
                            class="report-card-icon bg-white/10 text-[#7cc8ff]"
                        >
                            <BarChart3 class="size-5" />
                        </div>
                        <p class="report-card-label">Total Test Runs</p>
                        <p class="mt-3 text-5xl font-semibold">3</p>
                        <p class="mt-3 text-sm text-slate-300">Last 7 Days</p>
                    </div>
                    <div class="report-card">
                        <div
                            class="report-card-icon bg-white/10 text-[#ff9292]"
                        >
                            <Bug class="size-5" />
                        </div>
                        <p class="report-card-label">Total Issues</p>
                        <p class="mt-3 text-5xl font-semibold">1</p>
                        <p class="mt-3 text-sm text-slate-300">
                            Open and historical
                        </p>
                    </div>
                    <div class="report-card">
                        <div
                            class="report-card-icon bg-white/10 text-[#9dd8ff]"
                        >
                            <CheckCircle2 class="size-5" />
                        </div>
                        <p class="report-card-label">Execution Coverage</p>
                        <p class="mt-3 text-5xl font-semibold">0.0%</p>
                        <p class="mt-3 text-sm text-slate-300">
                            Executed case coverage
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <article class="report-panel">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-2xl font-semibold">Issue Trend</h3>
                            <div
                                class="inline-flex rounded-full bg-white/8 p-1 text-sm"
                            >
                                <span
                                    class="rounded-full bg-white px-4 py-1 font-semibold text-[#05284b]"
                                >
                                    {reportCadence}
                                </span>
                            </div>
                        </div>

                        <div class="mt-8 flex h-[260px] items-end gap-6">
                            {#each reportTrend as item (item.label)}
                                <div
                                    class="flex flex-1 flex-col items-center gap-3"
                                >
                                    <div
                                        class="w-full max-w-[52px] rounded-t-2xl bg-[#c7daf7]"
                                        style={`height: ${item.height}`}
                                    ></div>
                                    <span class="text-sm text-slate-300">
                                        {item.label}
                                    </span>
                                </div>
                            {/each}
                        </div>
                    </article>

                    <article class="report-panel">
                        <h3 class="text-2xl font-semibold">
                            Issue Distribution
                        </h3>
                        <div class="mt-8 grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
                            <div class="flex items-center justify-center">
                                <div
                                    class="flex h-44 w-44 items-center justify-center rounded-full border-[28px] border-[#ef2828] bg-white text-[#05284b]"
                                >
                                    <div class="text-center">
                                        <p class="text-4xl font-semibold">1</p>
                                        <p class="mt-2 text-sm text-slate-500">
                                            Total
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-3 self-center text-sm text-slate-200 sm:grid-cols-2"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-[#9eb7d4]"
                                    ></span>
                                    Low (0)
                                </div>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-[#f5a524]"
                                    ></span>
                                    Medium (0)
                                </div>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-[#ff5a7a]"
                                    ></span>
                                    High (0)
                                </div>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-[#ef2828]"
                                    ></span>
                                    Critical (1)
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <article class="report-panel">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-2xl font-semibold">
                                Test Results Summary
                            </h3>
                            <span class="text-sm text-slate-300"
                                >Last 7 Days</span
                            >
                        </div>
                        <div class="mt-7 h-11 rounded-full bg-[#c7d4e7]">
                            <div
                                class="flex h-full items-center justify-center rounded-full bg-[#c7d4e7] text-sm font-semibold text-[#35506e]"
                            >
                                100%
                            </div>
                        </div>
                        <div class="mt-7 grid gap-5 sm:grid-cols-5">
                            <div>
                                <p
                                    class="text-3xl font-semibold text-emerald-400"
                                >
                                    0
                                </p>
                                <p class="mt-1 text-sm text-slate-300">
                                    Passed
                                </p>
                            </div>
                            <div>
                                <p class="text-3xl font-semibold text-red-400">
                                    0
                                </p>
                                <p class="mt-1 text-sm text-slate-300">
                                    Failed
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-3xl font-semibold text-[#69a9ff]"
                                >
                                    0
                                </p>
                                <p class="mt-1 text-sm text-slate-300">
                                    In Testing
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-3xl font-semibold text-amber-300"
                                >
                                    0
                                </p>
                                <p class="mt-1 text-sm text-slate-300">
                                    Blocked
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-3xl font-semibold text-slate-200"
                                >
                                    26
                                </p>
                                <p class="mt-1 text-sm text-slate-300">
                                    Not Run
                                </p>
                            </div>
                        </div>
                    </article>

                    <article class="report-panel">
                        <h3 class="text-2xl font-semibold">Team Workload</h3>
                        <div class="mt-7 space-y-6">
                            {#each assignmentRows as row (row.tester)}
                                <div>
                                    <div
                                        class="flex items-center justify-between gap-4"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="assignment-avatar bg-white/10 text-white"
                                            >
                                                {row.tester
                                                    .split(' ')
                                                    .map((part) => part[0])
                                                    .join('')
                                                    .slice(0, 2)}
                                            </span>
                                            <div>
                                                <p class="font-semibold">
                                                    {row.tester}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm text-slate-300"
                                                >
                                                    {row.runs}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            class="text-sm font-medium text-slate-300"
                                        >
                                            {row.cases}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-3 h-3 rounded-full bg-white/10"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[#2a6cff]"
                                            style={`width: ${row.width}`}
                                        ></div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section
            class="px-5 py-20 text-center sm:px-8 lg:px-12 lg:py-24"
            aria-labelledby="cta-title"
        >
            <div class="mx-auto max-w-3xl">
                <h2
                    id="cta-title"
                    class="text-[clamp(2.6rem,5vw,4.75rem)] leading-[1.02] font-semibold tracking-[-0.055em] text-balance"
                >
                    Make quality visible before release day.
                </h2>
                <p
                    class="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300"
                >
                    Give QA leads, engineers, and product teams one place to
                    plan clearly, generate faster, and report with confidence.
                </p>
                <Link
                    href={toUrl(ctaHref)}
                    class="primary-marketing-button mt-9">{ctaLabel}</Link
                >
            </div>
        </section>
    </main>

    <footer
        class="border-t border-slate-200 px-5 py-10 sm:px-8 lg:px-12 dark:border-white/10"
    >
        <div
            class="mx-auto flex max-w-[1440px] flex-col gap-8 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-6">
                <AppLogo iconClass="size-8" wordmarkClass="text-2xl" />
                <span
                    class="hidden h-8 w-px bg-slate-300 sm:block dark:bg-white/15"
                ></span>
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    QA workspace
                </span>
            </div>
            <nav
                class="flex flex-wrap items-center gap-x-7 gap-y-3 text-sm font-medium sm:ml-auto"
                aria-label="Footer navigation"
            >
                {#each navigationLinks as link (link.href)}
                    <a class="marketing-link" href={link.href}>{link.label}</a>
                {/each}
                <Link
                    class="font-semibold text-[#0b5fff] hover:text-[#084ed7]"
                    href={toUrl(ctaHref)}>{ctaLabel}</Link
                >
            </nav>
        </div>
    </footer>
</div>

<style>
    :global(html) {
        scroll-behavior: smooth;
    }

    :global(html) {
        background: #fff;
    }

    :global(html.dark) {
        background: #04101e;
    }

    .marketing-link {
        border-radius: 0.35rem;
        transition: color 160ms ease;
    }

    .marketing-link:hover {
        color: #0b5fff;
    }

    .marketing-link:focus-visible,
    .mobile-nav-link:focus-visible {
        outline: 2px solid #0b5fff;
        outline-offset: 4px;
    }

    .mobile-nav-link {
        border-radius: 0.75rem;
        padding: 0.8rem 0.9rem;
        font-weight: 600;
    }

    .mobile-nav-link:hover {
        background: #eff6ff;
        color: #0b5fff;
    }

    :global(.dark) .mobile-nav-link:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    :global(.primary-marketing-button),
    .secondary-marketing-button {
        display: inline-flex;
        min-height: 3.5rem;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
        border-radius: 0.8rem;
        padding: 0 1.6rem;
        font-size: 0.95rem;
        font-weight: 650;
        transition:
            transform 160ms ease,
            background-color 160ms ease,
            border-color 160ms ease;
    }

    :global(.primary-marketing-button) {
        background: #0b5fff;
        color: white;
        box-shadow: 0 12px 28px rgba(11, 95, 255, 0.22);
    }

    :global(.primary-marketing-button:hover) {
        background: #084ed7;
        transform: translateY(-1px);
    }

    .secondary-marketing-button {
        border: 1px solid #c8d7ef;
        background: white;
        color: #0d386b;
    }

    .secondary-marketing-button:hover {
        background: #eff6ff;
        transform: translateY(-1px);
    }

    :global(.dark) .secondary-marketing-button {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.04);
        color: white;
    }

    :global(.dark) .secondary-marketing-button:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    :global(.primary-marketing-button:focus-visible),
    .secondary-marketing-button:focus-visible,
    .planning-tab:focus-visible,
    .report-toggle:focus-visible {
        outline: 2px solid #0b5fff;
        outline-offset: 3px;
    }

    .panel-kicker,
    .label-heading,
    .report-card-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .panel-kicker,
    .label-heading {
        color: #64748b;
    }

    :global(.dark) .panel-kicker,
    :global(.dark) .label-heading {
        color: #94a3b8;
    }

    .planning-tab,
    .report-toggle {
        border-radius: 0.9rem;
        padding: 0.85rem 1.05rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #64748b;
        transition:
            background-color 160ms ease,
            color 160ms ease;
    }

    .active-tab {
        background: #0b5fff;
        color: white;
        box-shadow: 0 8px 20px rgba(11, 95, 255, 0.2);
    }

    .report-toggle {
        color: #d6e5f5;
    }

    .report-toggle-active {
        background: white;
        color: #05284b;
    }

    .summary-card {
        display: flex;
        min-width: 0;
        min-height: 110px;
        flex-direction: column;
        border-radius: 1.1rem;
        border: 1px solid #dce6f4;
        background: white;
        padding: 1rem;
    }

    .summary-card span {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #64748b;
    }

    .summary-card strong {
        margin-top: 0.8rem;
        font-size: 2rem;
        line-height: 1;
    }

    .summary-card small {
        margin-top: auto;
        padding-top: 0.8rem;
        font-size: 0.85rem;
        color: #64748b;
    }

    :global(.dark) .summary-card {
        border-color: rgba(255, 255, 255, 0.12);
        background: #071525;
    }

    :global(.dark) .summary-card span,
    :global(.dark) .summary-card small {
        color: #94a3b8;
    }

    .workspace-table-header,
    .workspace-table-row {
        display: grid;
        gap: 1rem;
        align-items: start;
        padding: 1rem 1.25rem;
    }

    .workspace-table-header {
        grid-template-columns: 2fr 1fr auto 1.1fr;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fbff;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #64748b;
    }

    .workspace-table-row {
        grid-template-columns: 2fr 1fr auto 1.1fr;
        border-bottom: 1px solid #eef2f7;
        font-size: 0.95rem;
    }

    .workspace-table-row:last-child {
        border-bottom: 0;
    }

    .workspace-table-header-cases,
    .workspace-table-row-cases {
        grid-template-columns: 2fr 0.8fr auto 1fr;
    }

    .workspace-table-header-runs,
    .workspace-table-row-runs {
        grid-template-columns: 2fr 0.8fr auto auto 1fr;
    }

    .workspace-table-header-issues,
    .workspace-table-row-issues {
        grid-template-columns: 0.7fr 2fr auto auto auto;
    }

    :global(.dark) .workspace-table-header {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.03);
        color: #94a3b8;
    }

    :global(.dark) .workspace-table-row {
        border-color: rgba(255, 255, 255, 0.06);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .status-pill-blue {
        background: #eff6ff;
        color: #0b5fff;
    }

    .status-pill-red {
        background: #ffe4e8;
        color: #e11d48;
    }

    .status-pill-critical {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-pill-amber {
        background: #fef3c7;
        color: #b45309;
    }

    .status-pill-cyan {
        background: #e0f2fe;
        color: #0369a1;
    }

    .status-pill-slate {
        background: #edf2f7;
        color: #475569;
    }

    .assignment-row + .assignment-row {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }

    :global(.dark) .assignment-row + .assignment-row {
        border-color: rgba(255, 255, 255, 0.08);
    }

    .assignment-avatar {
        display: inline-flex;
        height: 2.5rem;
        width: 2.5rem;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #edf4ff;
        font-size: 0.82rem;
        font-weight: 700;
        color: #0d386b;
    }

    .report-card,
    .report-panel {
        border-radius: 1.75rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.04);
    }

    .report-card {
        padding: 1.35rem;
    }

    .report-panel {
        padding: 1.5rem;
    }

    .report-card-icon {
        display: inline-flex;
        height: 2.75rem;
        width: 2.75rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.95rem;
    }

    .skeleton {
        background: linear-gradient(
            90deg,
            rgba(226, 232, 240, 0.9),
            rgba(241, 245, 249, 1),
            rgba(226, 232, 240, 0.9)
        );
        background-size: 200% 100%;
        animation: shimmer 1.2s linear infinite;
    }

    :global(.dark) .skeleton {
        background: linear-gradient(
            90deg,
            rgba(30, 41, 59, 0.9),
            rgba(51, 65, 85, 1),
            rgba(30, 41, 59, 0.9)
        );
        background-size: 200% 100%;
    }

    @media (max-width: 1279px) {
        .workspace-table-header,
        .workspace-table-row,
        .workspace-table-header-cases,
        .workspace-table-row-cases,
        .workspace-table-header-runs,
        .workspace-table-row-runs,
        .workspace-table-header-issues,
        .workspace-table-row-issues {
            grid-template-columns: 1fr;
        }

        .workspace-table-header {
            display: none;
        }
    }

    @media (max-width: 639px) {
        :global(.primary-marketing-button),
        .secondary-marketing-button {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        :global(html) {
            scroll-behavior: auto;
        }

        .skeleton,
        :global(.primary-marketing-button),
        .secondary-marketing-button,
        .planning-tab,
        .report-toggle {
            animation: none;
            transition: none;
        }
    }

    @keyframes shimmer {
        from {
            background-position: 200% 0;
        }

        to {
            background-position: -200% 0;
        }
    }
</style>
