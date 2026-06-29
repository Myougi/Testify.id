<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { cn } from '@/lib/utils';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();

    const isLoginScreen = $derived(title === 'Log in to your account');
</script>

<div
    class="relative isolate flex min-h-svh items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.18),_transparent_34%),linear-gradient(180deg,_#f8fafc_0%,_#eef2ff_48%,_#ffffff_100%)] px-4 py-10 sm:px-6 lg:px-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_30%),linear-gradient(180deg,_#020617_0%,_#0f172a_48%,_#020617_100%)]"
>
    <div
        class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle,_rgba(255,255,255,0.9),_transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle,_rgba(15,23,42,0.85),_transparent_60%)]"
    ></div>
    <div
        class="pointer-events-none absolute -top-24 right-8 h-56 w-56 rounded-full bg-cyan-300/30 blur-3xl dark:bg-cyan-400/20"
    ></div>
    <div
        class="pointer-events-none absolute bottom-0 left-0 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl dark:bg-indigo-500/15"
    ></div>

    <div
        class="relative grid w-full max-w-6xl overflow-hidden rounded-[2rem] border border-white/60 bg-white/80 shadow-[0_30px_80px_rgba(15,23,42,0.12)] backdrop-blur-xl lg:grid-cols-[1.05fr_0.95fr] dark:border-white/10 dark:bg-slate-950/75 dark:shadow-[0_30px_80px_rgba(2,6,23,0.6)]"
    >
        <div
            class={cn(
                'relative hidden min-h-[720px] overflow-hidden border-r border-slate-200/70 px-10 py-12 lg:flex lg:flex-col lg:justify-between dark:border-white/10',
                isLoginScreen
                    ? 'bg-[linear-gradient(160deg,_rgba(2,132,199,0.98),_rgba(15,23,42,0.96))] text-white'
                    : 'bg-[linear-gradient(160deg,_rgba(15,23,42,0.96),_rgba(30,41,59,0.94))] text-slate-100',
            )}
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.22),_transparent_34%),radial-gradient(circle_at_bottom_left,_rgba(255,255,255,0.14),_transparent_32%)]"
            ></div>
            <div class="relative space-y-8">
                <Link
                    href={home()}
                    class="inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-medium text-white/90 backdrop-blur"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-slate-950 shadow-lg"
                    >
                        <AppLogoIcon class="size-6" />
                    </span>
                    <span>Testify workspace</span>
                </Link>

                <div class="max-w-md space-y-5">
                    <span
                        class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold tracking-[0.24em] text-white/75 uppercase"
                    >
                        Secure access
                    </span>
                    <div class="space-y-3">
                        <h1
                            class="text-4xl font-semibold tracking-tight text-balance"
                        >
                            {isLoginScreen
                                ? 'Welcome back to a calmer way to manage your work.'
                                : title}
                        </h1>
                        <p class="text-base leading-7 text-white/72">
                            {isLoginScreen
                                ? 'Pick up where you left off with a cleaner, faster sign-in experience built for focused teams.'
                                : description}
                        </p>
                    </div>
                </div>
            </div>

            <div class="relative space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        class="rounded-3xl border border-white/15 bg-white/10 p-5 backdrop-blur"
                    >
                        <p class="text-sm text-white/70">Session handling</p>
                        <p class="mt-2 text-2xl font-semibold">Encrypted</p>
                    </div>
                    <div
                        class="rounded-3xl border border-white/15 bg-white/10 p-5 backdrop-blur"
                    >
                        <p class="text-sm text-white/70">Team handoff</p>
                        <p class="mt-2 text-2xl font-semibold">Instant</p>
                    </div>
                </div>
                <p class="max-w-md text-sm leading-6 text-white/68">
                    Thoughtful details, strong contrast, and less visual noise
                    so the first screen feels modern without getting in your
                    way.
                </p>
            </div>
        </div>

        <div
            class="relative flex min-h-[720px] items-center px-5 py-8 sm:px-8 lg:px-12"
        >
            <div class="mx-auto w-full max-w-md">
                <div
                    class="mb-8 flex flex-col items-center gap-4 text-center lg:hidden"
                >
                    <Link
                        href={home()}
                        class="inline-flex items-center gap-3 rounded-full border border-slate-200/70 bg-white/80 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/60 dark:text-slate-100"
                    >
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white shadow-lg"
                        >
                            <AppLogoIcon class="size-6" />
                        </span>
                        <span>Testify workspace</span>
                    </Link>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-balance"
                        >
                            {title}
                        </h1>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-[1.75rem] border border-slate-200/70 bg-white/88 p-6 shadow-[0_18px_60px_rgba(15,23,42,0.08)] backdrop-blur md:p-8 dark:border-white/10 dark:bg-slate-950/70 dark:shadow-[0_18px_60px_rgba(2,6,23,0.45)]"
                >
                    <div class="mb-8 hidden space-y-3 text-left lg:block">
                        <p
                            class="text-sm font-medium text-sky-700 dark:text-sky-300"
                        >
                            Welcome back
                        </p>
                        <h2
                            class="text-3xl font-semibold tracking-tight text-balance"
                        >
                            {title}
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {@render children?.()}
                </div>
            </div>
        </div>
    </div>
</div>
