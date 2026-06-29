<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
        useSidebar,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        items = [],
    }: {
        items: NavItem[];
    } = $props();

    const url = currentUrlState();
    const { isMobile, setOpenMobile } = useSidebar();

    const handleNavigate = (): void => {
        if ($isMobile) {
            setOpenMobile(false);
        }
    };
</script>

<SidebarGroup class="px-3 py-3 group-data-[collapsible=icon]:px-2">
    <SidebarGroupLabel
        class="px-3 pb-2 text-[0.65rem] font-semibold tracking-[0.2em] text-white/40 uppercase"
    >
        Workspace
    </SidebarGroupLabel>
    <SidebarMenu class="gap-1">
        {#each items as item (toUrl(item.href))}
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.isCurrentUrl(item.href, url.currentUrl)}
                    class="relative h-10 rounded-xl px-3 text-sm font-medium text-white/68 transition-[background-color,color] duration-150 before:absolute before:inset-y-2 before:left-0 before:w-0.5 before:rounded-full before:bg-sky-300 before:opacity-0 before:transition-opacity before:duration-150 hover:bg-white/[0.06] hover:text-white focus-visible:ring-2 focus-visible:ring-sky-300/70 focus-visible:ring-offset-2 focus-visible:ring-offset-[#082341] data-[active=true]:bg-white/[0.09] data-[active=true]:font-semibold data-[active=true]:text-white data-[active=true]:before:opacity-100 [&>svg]:text-white/48 [&>svg]:transition-[color,transform] [&>svg]:duration-150 hover:[&>svg]:translate-x-0.5 hover:[&>svg]:text-white/72 data-[active=true]:[&>svg]:text-sky-200 group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:rounded-xl group-data-[collapsible=icon]:p-0! group-data-[collapsible=icon]:before:inset-y-2.5"
                    tooltip={item.title}
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(item.href)}
                            class={props.class}
                            onclick={handleNavigate}
                        >
                            {#if item.icon}
                                <item.icon class="size-4 shrink-0" />
                            {/if}
                            <span class="group-data-[collapsible=icon]:hidden">
                                {item.title}
                            </span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
