<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import Bug from 'lucide-svelte/icons/bug';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import CirclePlay from 'lucide-svelte/icons/circle-play';
    import Database from 'lucide-svelte/icons/database';
    import Folder from 'lucide-svelte/icons/folder';
    import GitBranch from 'lucide-svelte/icons/git-branch';
    import ListChecks from 'lucide-svelte/icons/list-checks';
    import Menu from 'lucide-svelte/icons/menu';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { toUrl } from '@/lib/utils';
    import { login } from '@/routes';
    import { index as projectsIndex } from '@/routes/projects';

    type PreviewTab = 'Plan' | 'Execute' | 'Report';

    const auth = $derived(page.props.auth);
    const ctaLabel = $derived(auth.user ? 'Open workspace' : 'Log in');
    const ctaHref = $derived(auth.user ? projectsIndex() : login());

    let activeTab = $state<PreviewTab>('Plan');
    let mobileMenuOpen = $state(false);

    const previewTabs: PreviewTab[] = ['Plan', 'Execute', 'Report'];
    const repositories = [
        ['Authentication', '48'],
        ['Cart & Checkout', '92'],
        ['Payments', '76'],
        ['Orders', '56'],
    ];
    const executions = [
        ['E-24', 'Smoke run', 'Passed'],
        ['E-23', 'Checkout flow', 'Failed'],
        ['E-22', 'Payment methods', 'Passed'],
        ['E-21', 'Regression suite', 'Passed'],
    ];
    const issues = [
        ['PAY-142', '3DS challenge fails', 'High'],
        ['CHK-208', 'Promo code not applied', 'Medium'],
        ['UI-198', 'Totals misaligned on mobile', 'Low'],
    ];
    const workflowStages = [
        {
            number: '01',
            title: 'Organize reusable cases',
            copy: 'Build and maintain modular test repositories your team can trust and reuse across projects.',
            icon: ListChecks,
        },
        {
            number: '02',
            title: 'Coordinate focused runs',
            copy: 'Create targeted test plans, run with confidence, and get real-time visibility across environments.',
            icon: CirclePlay,
        },
        {
            number: '03',
            title: 'Turn failures into release signals',
            copy: 'Track issues to resolution and measure release readiness with reports your team can rely on.',
            icon: BarChart3,
        },
    ];

    const closeMobileMenu = (): void => {
        mobileMenuOpen = false;
    };
</script>

<AppHead title="QA workspace for confident releases">
    <meta
        name="description"
        content="Plan test coverage, coordinate executions, track issues, and report release readiness in Testify."
    />
</AppHead>

<div
    class="marketing-page min-h-screen overflow-x-hidden bg-white text-[#071a2f] dark:bg-[#020c18] dark:text-slate-50"
>
    <header
        class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl dark:border-white/10 dark:bg-[#020c18]/92"
    >
        <div
            class="mx-auto flex h-[76px] w-full max-w-[1440px] items-center px-5 sm:px-8 lg:px-12"
        >
            <a
                href="#top"
                class="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5fff] focus-visible:ring-offset-4 dark:focus-visible:ring-offset-[#020c18]"
                aria-label="Testify home"
            >
                <AppLogo iconClass="size-8" wordmarkClass="text-[1.7rem]" />
            </a>

            <nav
                class="ml-16 hidden items-center gap-10 text-sm font-medium text-slate-600 lg:flex dark:text-slate-300"
                aria-label="Main navigation"
            >
                <a class="marketing-link" href="#product">Product</a>
                <a class="marketing-link" href="#workflow">Workflow</a>
                <a class="marketing-link" href="#features">Features</a>
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
                class="border-t border-slate-200 bg-white px-5 py-4 lg:hidden dark:border-white/10 dark:bg-[#061426]"
                aria-label="Mobile navigation"
            >
                <div class="mx-auto grid max-w-[1440px] gap-1">
                    <a
                        class="mobile-nav-link"
                        href="#product"
                        onclick={closeMobileMenu}>Product</a
                    >
                    <a
                        class="mobile-nav-link"
                        href="#workflow"
                        onclick={closeMobileMenu}>Workflow</a
                    >
                    <a
                        class="mobile-nav-link"
                        href="#features"
                        onclick={closeMobileMenu}>Features</a
                    >
                </div>
            </nav>
        {/if}
    </header>

    <main id="top">
        <section
            class="mx-auto grid w-full max-w-[1440px] items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.78fr_1.22fr] lg:gap-16 lg:px-12 lg:py-24"
            aria-labelledby="hero-title"
        >
            <div class="hero-copy min-w-0 max-w-[590px]">
                <h1
                    id="hero-title"
                    class="text-[2.55rem] leading-[1.02] font-semibold tracking-[-0.05em] text-balance sm:text-[clamp(3rem,5.1vw,5.25rem)] sm:leading-[0.98] sm:tracking-[-0.055em]"
                >
                    Plan, run, and prove quality in one workspace.
                </h1>
                <p
                    class="mt-7 max-w-[570px] text-lg leading-8 text-slate-600 dark:text-slate-300"
                >
                    Testify gives QA teams one connected place to organize test
                    cases, coordinate executions, track issues, and report
                    release readiness.
                </p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <Link
                        href={toUrl(ctaHref)}
                        class="primary-marketing-button"
                    >
                        {ctaLabel}
                    </Link>
                    <a href="#workflow" class="secondary-marketing-button">
                        See the workflow
                        <ArrowRight class="size-4" />
                    </a>
                </div>
            </div>

            <div id="product" class="min-w-0 scroll-mt-28 lg:pt-2">
                <div
                    class="product-window overflow-hidden rounded-xl border border-slate-300 bg-white shadow-[0_26px_70px_rgba(7,26,47,0.14),0_2px_8px_rgba(7,26,47,0.08)] dark:border-white/15 dark:bg-[#071a2f]"
                >
                    <div class="grid sm:grid-cols-[132px_1fr]">
                        <aside
                            class="hidden min-h-[570px] flex-col bg-[linear-gradient(180deg,#082c57_0%,#041a33_100%)] text-white sm:flex"
                            aria-label="Preview navigation"
                        >
                            <div
                                class="flex h-[82px] items-center justify-center border-b border-white/10"
                            >
                                <AppLogoIcon class="size-8" />
                            </div>
                            <div class="space-y-1 p-3 text-[12px]">
                                <div
                                    class="preview-nav-item bg-[#0b5fff] text-white"
                                >
                                    <Folder class="size-4" /> Projects
                                </div>
                                <div class="preview-nav-item">
                                    <Database class="size-4" /> Repositories
                                </div>
                                <div class="preview-nav-item">
                                    <ListChecks class="size-4" /> Test plans
                                </div>
                                <div class="preview-nav-item">
                                    <CirclePlay class="size-4" /> Executions
                                </div>
                                <div class="preview-nav-item">
                                    <Bug class="size-4" /> Issues
                                </div>
                                <div class="preview-nav-item">
                                    <BarChart3 class="size-4" /> Reports
                                </div>
                            </div>
                        </aside>

                        <div class="min-w-0 p-4 sm:p-6">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <h2
                                    class="truncate text-lg font-semibold sm:text-xl"
                                >
                                    Checkout regression
                                </h2>
                                <ChevronDown
                                    class="size-4 shrink-0 text-slate-500"
                                />
                            </div>

                            <div
                                class="mt-5 flex gap-6 border-b border-slate-200 dark:border-white/10"
                                role="tablist"
                                aria-label="Product preview"
                            >
                                {#each previewTabs as tab (tab)}
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={activeTab === tab}
                                        class:active-preview-tab={activeTab ===
                                            tab}
                                        class="preview-tab"
                                        onclick={() => (activeTab = tab)}
                                    >
                                        {tab}
                                    </button>
                                {/each}
                            </div>

                            {#key activeTab}
                                <div class="preview-content pt-5">
                                    {#if activeTab === 'Plan'}
                                        <div
                                            class="grid grid-cols-2 gap-3 lg:grid-cols-4"
                                        >
                                            <div class="metric-panel">
                                                <span>Test cases</span><strong
                                                    >312</strong
                                                >
                                                <div class="metric-line">
                                                    <i style="width: 78%"></i>
                                                </div>
                                                <small>78% covered</small>
                                            </div>
                                            <div class="metric-panel">
                                                <span>Executions</span><strong
                                                    >24</strong
                                                >
                                                <div
                                                    class="metric-line metric-line-green"
                                                >
                                                    <i style="width: 64%"></i>
                                                </div>
                                                <small>In progress</small>
                                            </div>
                                            <div class="metric-panel">
                                                <span>Pass rate</span><strong
                                                    >91%</strong
                                                >
                                                <div
                                                    class="metric-line metric-line-green"
                                                >
                                                    <i style="width: 91%"></i>
                                                </div>
                                                <small
                                                    >21 passed / 3 failed</small
                                                >
                                            </div>
                                            <div class="metric-panel">
                                                <span>Release readiness</span
                                                ><strong
                                                    class="!text-emerald-600"
                                                    >Ready</strong
                                                >
                                                <small class="mt-auto"
                                                    >Target: May 30</small
                                                >
                                            </div>
                                        </div>

                                        <div
                                            class="mt-3 grid gap-3 lg:grid-cols-3"
                                        >
                                            <div class="preview-panel">
                                                <h3>Repository modules</h3>
                                                <div class="mt-4 space-y-3">
                                                    {#each repositories as repository (repository[0])}
                                                        <div
                                                            class="preview-row"
                                                        >
                                                            <span
                                                                class="flex min-w-0 items-center gap-2 truncate"
                                                                ><Folder
                                                                    class="size-3.5"
                                                                />
                                                                {repository[0]}</span
                                                            >
                                                            <span
                                                                >{repository[1]}</span
                                                            >
                                                        </div>
                                                    {/each}
                                                </div>
                                                <a
                                                    href="#features"
                                                    class="preview-link"
                                                    >View all</a
                                                >
                                            </div>
                                            <div class="preview-panel">
                                                <h3>Recent executions</h3>
                                                <div class="mt-4 space-y-3">
                                                    {#each executions as execution (execution[0])}
                                                        <div
                                                            class="preview-row items-start"
                                                        >
                                                            <span
                                                                ><b
                                                                    >{execution[0]}</b
                                                                ><small
                                                                    >{execution[1]}</small
                                                                ></span
                                                            >
                                                            <span
                                                                class:status-failed={execution[2] ===
                                                                    'Failed'}
                                                                class="status-label"
                                                                >{execution[2]}</span
                                                            >
                                                        </div>
                                                    {/each}
                                                </div>
                                                <button
                                                    type="button"
                                                    class="preview-link"
                                                    onclick={() =>
                                                        (activeTab = 'Execute')}
                                                    >View executions</button
                                                >
                                            </div>
                                            <div class="preview-panel lg:block">
                                                <h3>Recent issues</h3>
                                                <div class="mt-4 space-y-3">
                                                    {#each issues as issue (issue[0])}
                                                        <div
                                                            class="preview-row items-start"
                                                        >
                                                            <span
                                                                ><b
                                                                    >{issue[0]}</b
                                                                ><small
                                                                    >{issue[1]}</small
                                                                ></span
                                                            >
                                                            <span
                                                                class="issue-label"
                                                                >{issue[2]}</span
                                                            >
                                                        </div>
                                                    {/each}
                                                </div>
                                                <a
                                                    href="#features"
                                                    class="preview-link"
                                                    >View issues</a
                                                >
                                            </div>
                                        </div>
                                    {:else if activeTab === 'Execute'}
                                        <div
                                            class="preview-panel min-h-[394px]"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-4"
                                            >
                                                <div>
                                                    <h3>
                                                        Focused regression run
                                                    </h3>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        Staging - Chrome - 24
                                                        cases
                                                    </p>
                                                </div>
                                                <span
                                                    class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#0b5fff] dark:bg-blue-500/15"
                                                    >In progress</span
                                                >
                                            </div>
                                            <div
                                                class="mt-6 h-2 rounded-full bg-slate-100 dark:bg-white/10"
                                            >
                                                <div
                                                    class="h-full w-[64%] rounded-full bg-[#0b5fff]"
                                                ></div>
                                            </div>
                                            <div
                                                class="mt-6 divide-y divide-slate-100 dark:divide-white/10"
                                            >
                                                {#each executions as execution, index (execution[0])}
                                                    <div
                                                        class="flex items-center gap-3 py-4"
                                                    >
                                                        <CheckCircle2
                                                            class={execution[2] ===
                                                            'Failed'
                                                                ? 'size-5 text-red-500'
                                                                : 'size-5 text-emerald-500'}
                                                        />
                                                        <div
                                                            class="min-w-0 flex-1"
                                                        >
                                                            <p
                                                                class="truncate text-sm font-semibold"
                                                            >
                                                                {execution[1]}
                                                            </p>
                                                            <p
                                                                class="mt-1 text-xs text-slate-500"
                                                            >
                                                                TC-{325 + index} -
                                                                {index + 3} steps
                                                            </p>
                                                        </div>
                                                        <span
                                                            class:status-failed={execution[2] ===
                                                                'Failed'}
                                                            class="status-label"
                                                            >{execution[2]}</span
                                                        >
                                                    </div>
                                                {/each}
                                            </div>
                                        </div>
                                    {:else}
                                        <div
                                            class="grid min-h-[394px] gap-3 md:grid-cols-[0.9fr_1.1fr]"
                                        >
                                            <div
                                                class="preview-panel flex flex-col justify-between"
                                            >
                                                <div>
                                                    <p
                                                        class="text-xs font-medium text-slate-500"
                                                    >
                                                        Release readiness
                                                    </p>
                                                    <p
                                                        class="mt-4 text-5xl font-semibold tracking-tight text-emerald-600"
                                                    >
                                                        91%
                                                    </p>
                                                    <p
                                                        class="mt-2 text-sm text-slate-500"
                                                    >
                                                        Ready with 3 known
                                                        issues
                                                    </p>
                                                </div>
                                                <div class="space-y-3 text-sm">
                                                    <div
                                                        class="flex justify-between"
                                                    >
                                                        <span>Passed</span
                                                        ><strong
                                                            class="text-emerald-600"
                                                            >281</strong
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex justify-between"
                                                    >
                                                        <span>Failed</span
                                                        ><strong
                                                            class="text-red-500"
                                                            >12</strong
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex justify-between"
                                                    >
                                                        <span>Blocked</span
                                                        ><strong>8</strong>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preview-panel">
                                                <h3>Coverage by module</h3>
                                                <div class="mt-6 space-y-5">
                                                    {#each [['Authentication', 96], ['Checkout', 88], ['Payments', 91], ['Orders', 84]] as module (module[0])}
                                                        <div>
                                                            <div
                                                                class="flex justify-between text-xs"
                                                            >
                                                                <span
                                                                    >{module[0]}</span
                                                                ><strong
                                                                    >{module[1]}%</strong
                                                                >
                                                            </div>
                                                            <div
                                                                class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-white/10"
                                                            >
                                                                <div
                                                                    class="h-full rounded-full bg-[#0b5fff]"
                                                                    style={`width: ${module[1]}%`}
                                                                ></div>
                                                            </div>
                                                        </div>
                                                    {/each}
                                                </div>
                                            </div>
                                        </div>
                                    {/if}
                                </div>
                            {/key}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="join-divider"><span></span></div>

        <section
            id="workflow"
            class="scroll-mt-24 px-5 py-24 sm:px-8 lg:py-32"
            aria-labelledby="workflow-title"
        >
            <div class="mx-auto max-w-[1240px]">
                <h2 id="workflow-title" class="section-title text-center">
                    From test case to release signal.
                </h2>
                <div class="mt-20 grid gap-16 lg:grid-cols-3 lg:gap-12">
                    {#each workflowStages as stage, index (stage.number)}
                        <article class="workflow-stage relative text-center">
                            <span class="stage-number">{stage.number}</span>
                            <div
                                class="mx-auto mt-8 flex size-24 items-center justify-center text-[#0b5fff]"
                            >
                                <stage.icon class="size-16 stroke-[1.35]" />
                            </div>
                            <h3
                                class="mt-7 text-xl font-semibold tracking-tight"
                            >
                                {stage.title}
                            </h3>
                            <p
                                class="mx-auto mt-4 max-w-[340px] leading-7 text-slate-600 dark:text-slate-300"
                            >
                                {stage.copy}
                            </p>
                            {#if index < workflowStages.length - 1}
                                <div
                                    class="workflow-connector"
                                    aria-hidden="true"
                                >
                                    <span></span>
                                </div>
                            {/if}
                        </article>
                    {/each}
                </div>
            </div>
        </section>

        <section
            id="features"
            class="scroll-mt-24"
            aria-label="Testify features"
        >
            <article class="feature-band bg-slate-50/60 dark:bg-[#061426]">
                <div class="feature-grid">
                    <div class="product-fragment order-2 lg:order-1">
                        <div class="fragment-header">
                            <span>Repositories</span><span
                                class="fragment-action">+ Add repository</span
                            >
                        </div>
                        {#each repositories as repository, index (repository[0])}
                            <div class="fragment-row">
                                <span class="flex items-center gap-3"
                                    ><Folder class="size-4 text-[#0b5fff]" />
                                    {repository[0]}</span
                                ><span class="text-slate-500"
                                    >{[
                                        'Core Services',
                                        'Commerce',
                                        'Finance',
                                        'Fulfillment',
                                    ][index]}</span
                                ><span class="font-medium"
                                    >{repository[1]} cases</span
                                >
                            </div>
                        {/each}
                    </div>
                    <div class="order-1 lg:order-2">
                        <div class="feature-icon">
                            <Database class="size-5" />
                        </div>
                        <h2 class="feature-title">
                            Build a shared source of truth.
                        </h2>
                        <p class="feature-copy">
                            Centralize test cases in structured repositories.
                            Organize by domain, keep steps clear, and maintain
                            quality as your product grows.
                        </p>
                    </div>
                </div>
            </article>

            <div class="join-divider join-divider-navy"><span></span></div>

            <article class="feature-band bg-[#05274b] text-white">
                <div class="feature-grid">
                    <div>
                        <div class="feature-icon feature-icon-dark">
                            <CirclePlay class="size-5" />
                        </div>
                        <h2 class="feature-title text-white">
                            Keep every run moving.
                        </h2>
                        <p class="feature-copy text-slate-300">
                            Plan with precision, execute with clarity, and
                            monitor progress in real time. Know what is running,
                            what failed, and what comes next.
                        </p>
                    </div>
                    <div
                        class="product-fragment border-white/15 bg-white text-[#071a2f] shadow-[0_24px_60px_rgba(1,10,22,0.3)]"
                    >
                        <div class="fragment-header">
                            <span>Executions</span><span class="fragment-action"
                                >+ New execution</span
                            >
                        </div>
                        {#each executions as execution, index (execution[0])}
                            <div
                                class="fragment-row grid-cols-[1fr_1fr_auto] sm:grid"
                            >
                                <span
                                    ><b>{execution[0]}</b> - {execution[1]}</span
                                ><span
                                    ><span
                                        class="inline-block h-1.5 w-24 rounded-full bg-slate-100"
                                        ><i
                                            class="block h-full rounded-full bg-[#0b5fff]"
                                            style={`width: ${[64, 100, 100, 78][index]}%`}
                                        ></i></span
                                    ></span
                                ><span
                                    class:status-failed={execution[2] ===
                                        'Failed'}
                                    class="status-label">{execution[2]}</span
                                >
                            </div>
                        {/each}
                    </div>
                </div>
            </article>

            <div class="join-divider join-divider-up"><span></span></div>

            <article class="feature-band dark:bg-[#020c18]">
                <div class="feature-grid">
                    <div class="product-fragment order-2 lg:order-1">
                        <div class="fragment-header">
                            <span>PAY-142 - 3DS challenge fails</span><span
                                class="issue-label">High</span
                            >
                        </div>
                        <div class="grid gap-5 p-5 sm:grid-cols-[1fr_0.85fr]">
                            <div>
                                <p class="fragment-label">Steps to reproduce</p>
                                <ol
                                    class="mt-3 list-decimal space-y-2 pl-4 text-sm text-slate-600"
                                >
                                    <li>Add an item to cart</li>
                                    <li>Proceed to checkout</li>
                                    <li>Complete 3DS challenge</li>
                                </ol>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-4">
                                <p class="fragment-label">
                                    Linked test results
                                </p>
                                <div class="mt-3 space-y-3">
                                    <div class="preview-row">
                                        <span>TC-325 - Visa</span><span
                                            class="status-label status-failed"
                                            >Failed</span
                                        >
                                    </div>
                                    <div class="preview-row">
                                        <span>TC-326 - Mastercard</span><span
                                            class="status-label status-failed"
                                            >Failed</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="order-1 lg:order-2">
                        <div class="feature-icon">
                            <GitBranch class="size-5" />
                        </div>
                        <h2 class="feature-title">
                            Turn failures into decisions.
                        </h2>
                        <p class="feature-copy">
                            Capture issues with context, link them to test
                            results, and prioritize with impact. Move from red
                            to green with confidence.
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <div class="join-divider"><span></span></div>

        <section
            class="px-5 py-24 text-center sm:px-8 lg:py-32"
            aria-labelledby="cta-title"
        >
            <div class="mx-auto max-w-3xl">
                <h2
                    id="cta-title"
                    class="text-[clamp(2.6rem,5vw,4.75rem)] font-semibold leading-[1.02] tracking-[-0.055em] text-balance"
                >
                    Make quality visible before release day.
                </h2>
                <Link
                    href={toUrl(ctaHref)}
                    class="primary-marketing-button mt-9">{ctaLabel}</Link
                >
            </div>
        </section>
    </main>

    <div class="join-divider"><span></span></div>

    <footer class="px-5 py-10 sm:px-8 lg:px-12">
        <div
            class="mx-auto flex max-w-[1440px] flex-col gap-8 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-6">
                <AppLogo iconClass="size-8" wordmarkClass="text-2xl" />
                <span
                    class="hidden h-8 w-px bg-slate-300 sm:block dark:bg-white/15"
                ></span>
                <span class="text-sm text-slate-500 dark:text-slate-400"
                    >QA workspace</span
                >
            </div>
            <nav
                class="flex flex-wrap items-center gap-x-7 gap-y-3 text-sm font-medium sm:ml-auto"
                aria-label="Footer navigation"
            >
                <a class="marketing-link" href="#product">Product</a>
                <a class="marketing-link" href="#workflow">Workflow</a>
                <a class="marketing-link" href="#features">Features</a>
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
    :global(html:has(.marketing-page)) {
        background: #fff;
    }
    :global(.dark html:has(.marketing-page)) {
        background: #020c18;
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
        border-radius: 0.5rem;
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
        border-radius: 0.65rem;
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
        border: 1px solid #0b5fff;
        color: #0b5fff;
    }
    .secondary-marketing-button:hover {
        background: #eff6ff;
        transform: translateY(-1px);
    }
    :global(.dark) .secondary-marketing-button:hover {
        background: rgba(59, 130, 246, 0.12);
    }
    :global(.primary-marketing-button:focus-visible),
    .secondary-marketing-button:focus-visible,
    .preview-tab:focus-visible,
    .preview-link:focus-visible {
        outline: 2px solid #0b5fff;
        outline-offset: 3px;
    }
    .hero-copy {
        animation: reveal-up 680ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }
    .product-window {
        animation: reveal-up 760ms 90ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }
    .preview-nav-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        border-radius: 0.4rem;
        padding: 0.7rem 0.75rem;
        color: rgba(255, 255, 255, 0.72);
    }
    .preview-tab {
        position: relative;
        padding: 0.1rem 0.1rem 0.75rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
    }
    .preview-tab::after {
        position: absolute;
        right: 0;
        bottom: -1px;
        left: 0;
        height: 2px;
        content: '';
        transform: scaleX(0);
        background: #0b5fff;
        transition: transform 160ms ease;
    }
    .active-preview-tab {
        color: #0b5fff;
    }
    .active-preview-tab::after {
        transform: scaleX(1);
    }
    .preview-content {
        animation: preview-enter 220ms ease both;
    }
    .metric-panel,
    .preview-panel {
        display: flex;
        min-width: 0;
        flex-direction: column;
        border: 1px solid #e2e8f0;
        border-radius: 0.6rem;
        background: #fff;
        padding: 0.9rem;
    }
    .metric-panel {
        min-height: 112px;
    }
    .metric-panel span {
        font-size: 0.68rem;
        color: #64748b;
    }
    .metric-panel strong {
        margin-top: 0.5rem;
        font-size: 1.45rem;
        line-height: 1;
    }
    .metric-panel small {
        margin-top: 0.45rem;
        font-size: 0.62rem;
        color: #64748b;
    }
    .metric-line {
        margin-top: 0.65rem;
        height: 3px;
        border-radius: 999px;
        background: #e2e8f0;
    }
    .metric-line i {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: #0b5fff;
    }
    .metric-line-green i {
        background: #16a34a;
    }
    .preview-panel {
        min-height: 230px;
    }
    .preview-panel h3,
    .fragment-header {
        font-size: 0.76rem;
        font-weight: 700;
    }
    .preview-row {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        font-size: 0.67rem;
    }
    .preview-row small {
        display: block;
        margin-top: 0.15rem;
        color: #64748b;
    }
    .preview-link {
        margin-top: auto;
        padding-top: 1rem;
        text-align: left;
        font-size: 0.66rem;
        font-weight: 700;
        color: #0b5fff;
    }
    .status-label,
    .issue-label {
        flex-shrink: 0;
        border-radius: 0.35rem;
        background: #dcfce7;
        padding: 0.2rem 0.35rem;
        font-size: 0.58rem;
        font-weight: 700;
        color: #15803d;
    }
    .status-failed {
        background: #fee2e2 !important;
        color: #dc2626 !important;
    }
    .issue-label {
        background: #fff7ed;
        color: #ea580c;
    }
    .join-divider {
        position: relative;
        height: 18px;
        border-top: 1.5px solid #0b5fff;
    }
    .join-divider > span {
        position: absolute;
        top: -1.5px;
        left: 50%;
        width: 30px;
        height: 14px;
        transform: translateX(-50%);
        border: 1.5px solid #0b5fff;
        border-top: 0;
        border-radius: 0 0 5px 5px;
        background: white;
    }
    .join-divider-navy > span {
        background: #05274b;
    }
    .join-divider-up > span {
        top: -14px;
        border: 1.5px solid #0b5fff;
        border-bottom: 0;
        border-radius: 5px 5px 0 0;
        background: #05274b;
    }
    :global(.dark) .join-divider > span {
        background: #020c18;
    }
    :global(.dark) .join-divider-navy > span,
    :global(.dark) .join-divider-up > span {
        background: #05274b;
    }
    .section-title {
        font-size: clamp(2.3rem, 4vw, 4rem);
        font-weight: 650;
        line-height: 1.05;
        letter-spacing: -0.045em;
    }
    .stage-number {
        display: inline-flex;
        height: 2.6rem;
        min-width: 2.6rem;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #0b5fff;
        padding: 0 0.7rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: white;
    }
    .workflow-connector {
        position: absolute;
        top: 5.35rem;
        right: -2.6rem;
        left: calc(50% + 5rem);
        display: none;
        height: 12px;
        border-top: 1px solid #0b5fff;
    }
    .workflow-connector span {
        position: absolute;
        top: -1px;
        right: 38%;
        width: 20px;
        height: 8px;
        border: 1px solid #0b5fff;
        border-top: 0;
        border-radius: 0 0 4px 4px;
        background: white;
    }
    :global(.dark) .workflow-connector span {
        background: #020c18;
    }
    .feature-band {
        padding: 6rem 1.25rem;
    }
    .feature-grid {
        margin: 0 auto;
        display: grid;
        max-width: 1240px;
        align-items: center;
        gap: 4rem;
    }
    .feature-title {
        margin-top: 1.4rem;
        max-width: 560px;
        font-size: clamp(2.3rem, 4vw, 4.25rem);
        font-weight: 650;
        line-height: 1.03;
        letter-spacing: -0.05em;
    }
    .feature-copy {
        margin-top: 1.5rem;
        max-width: 560px;
        font-size: 1.06rem;
        line-height: 1.9rem;
        color: #64748b;
    }
    .feature-icon {
        display: inline-flex;
        height: 2.8rem;
        width: 2.8rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.65rem;
        background: #eff6ff;
        color: #0b5fff;
    }
    .feature-icon-dark {
        background: rgba(255, 255, 255, 0.1);
        color: #60a5fa;
    }
    .product-fragment {
        overflow: hidden;
        border: 1px solid #cbd5e1;
        border-radius: 0.7rem;
        background: white;
        color: #071a2f;
        box-shadow: 0 18px 45px rgba(7, 26, 47, 0.1);
    }
    .fragment-header {
        display: flex;
        min-height: 3.6rem;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-bottom: 1px solid #e2e8f0;
        padding: 0 1.25rem;
    }
    .fragment-action {
        border-radius: 0.4rem;
        background: #0b5fff;
        padding: 0.45rem 0.7rem;
        font-size: 0.65rem;
        color: white;
    }
    .fragment-row {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr auto;
        gap: 1rem;
        border-bottom: 1px solid #f1f5f9;
        padding: 1rem 1.25rem;
        font-size: 0.72rem;
    }
    .fragment-row:last-child {
        border-bottom: 0;
    }
    .fragment-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #475569;
    }
    :global(.dark) .metric-panel,
    :global(.dark) .preview-panel {
        border-color: rgba(255, 255, 255, 0.12);
        background: #0a2038;
    }
    :global(.dark) .product-fragment {
        border-color: rgba(255, 255, 255, 0.15);
    }
    @media (min-width: 1024px) {
        .workflow-connector {
            display: block;
        }
        .feature-grid {
            grid-template-columns: 1.12fr 0.88fr;
        }
        .feature-band {
            padding: 7.5rem 3rem;
        }
    }
    @media (max-width: 639px) {
        :global(.primary-marketing-button),
        .secondary-marketing-button {
            width: 100%;
        }
        .product-window {
            margin-right: -4px;
            margin-left: -4px;
        }
        .metric-panel:nth-child(3),
        .metric-panel:nth-child(4) {
            display: none;
        }
        .fragment-row {
            grid-template-columns: 1fr auto;
        }
        .fragment-row > :nth-child(2) {
            display: none;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        :global(html) {
            scroll-behavior: auto;
        }
        .hero-copy,
        .product-window,
        .preview-content {
            animation: none;
        }
        :global(.primary-marketing-button),
        .secondary-marketing-button {
            transition: none;
        }
    }
    @keyframes reveal-up {
        from {
            opacity: 0;
            transform: translateY(18px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    @keyframes preview-enter {
        from {
            opacity: 0.5;
            transform: translateY(4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
