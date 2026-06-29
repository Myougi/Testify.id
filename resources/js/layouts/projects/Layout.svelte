<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import {
        Avatar,
        AvatarFallback,
        AvatarImage,
    } from '@/components/ui/avatar';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Toaster } from '@/components/ui/sonner';
    import UserMenuContent from '@/components/UserMenuContent.svelte';
    import { getInitials } from '@/lib/initials';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const auth = $derived(page.props.auth);
    const appName = import.meta.env.VITE_APP_NAME || 'Testify';
</script>

<AppShell variant="header">
    <header class="border-b border-slate-200 bg-white">
        <div
            class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
        >
            <div class="flex items-center gap-3 text-[#071a2f]">
                <div class="flex size-8 items-center justify-center">
                    <AppLogoIcon class="size-7" />
                </div>
                <span class="text-sm font-semibold">{appName}</span>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            variant="ghost"
                            class="h-10 gap-3 rounded-full px-2 text-[#071a2f] hover:bg-slate-100"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                            data-state={props['data-state']}
                        >
                            <Avatar class="size-8 overflow-hidden rounded-full">
                                {#if auth.user?.avatar}
                                    <AvatarImage
                                        src={String(auth.user.avatar)}
                                        alt={String(auth.user?.name ?? 'User')}
                                    />
                                {/if}
                                <AvatarFallback
                                    class="bg-[#002b57] font-semibold text-white"
                                >
                                    {getInitials(String(auth.user?.name ?? ''))}
                                </AvatarFallback>
                            </Avatar>
                            <span
                                class="hidden text-sm font-semibold sm:inline"
                            >
                                {auth.user?.name}
                            </span>
                        </Button>
                    {/snippet}
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 bg-white">
                    {#if auth.user}
                        <UserMenuContent user={auth.user} />
                    {/if}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>

    <AppContent
        variant="header"
        class="max-w-none gap-0 rounded-none bg-[#f4f7fa]"
    >
        {@render children?.()}
    </AppContent>
    <Toaster />
</AppShell>
