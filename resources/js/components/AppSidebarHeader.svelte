<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
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
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import UserMenuContent from '@/components/UserMenuContent.svelte';
    import { getInitials } from '@/lib/initials';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    const auth = $derived(page.props.auth);
    const appName = $derived(String(page.props.name ?? 'Testify'));
    const activeProjectName = $derived(
        String(page.props.activeProject?.name ?? appName),
    );
</script>

<header
    class="sticky top-0 z-20 shrink-0 border-b border-slate-200 bg-white/95 backdrop-blur"
>
    <div
        class="flex h-16 items-center justify-between gap-4 px-4 transition-[height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 sm:px-6 lg:px-8"
    >
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger
                class="size-10 rounded-full border border-slate-200 text-[#0a2747] hover:bg-slate-100 hover:text-[#061b33] lg:size-9 lg:border-0"
            />
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold text-[#071a2f]">
                    {activeProjectName}
                </p>
                <p class="truncate text-sm text-slate-500 lg:hidden">
                    {appName}
                </p>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            variant="ghost"
                            class="h-10 rounded-full px-2 text-[#071a2f] hover:bg-slate-100"
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
    </div>

    {#if breadcrumbs.length > 1}
        <div class="border-t border-slate-200/80 px-4 sm:px-6 lg:px-8">
            <div class="flex h-11 items-center text-sm text-slate-500">
                <Breadcrumbs {breadcrumbs} />
            </div>
        </div>
    {/if}
</header>
