<script lang="ts">
    import {
        SidebarGroup,
        SidebarGroupContent,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
        useSidebar,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        items = [],
        class: className = '',
    }: {
        items: NavItem[];
        class?: string;
    } = $props();

    const { isMobile, setOpenMobile } = useSidebar();

    const handleNavigate = (): void => {
        if ($isMobile) {
            setOpenMobile(false);
        }
    };
</script>

<SidebarGroup class={`group-data-[collapsible=icon]:p-0 ${className}`}>
    <SidebarGroupContent>
        <SidebarMenu class="gap-0.5">
            {#each items as item (toUrl(item.href))}
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="h-10 rounded-xl px-3 text-sm text-white/58 transition-[background-color,color] duration-150 hover:bg-white/[0.06] hover:text-white focus-visible:ring-2 focus-visible:ring-sky-300/70 focus-visible:ring-offset-2 focus-visible:ring-offset-[#082341] [&>svg]:text-white/42 [&>svg]:transition-colors [&>svg]:duration-150 hover:[&>svg]:text-white/70 group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:rounded-xl group-data-[collapsible=icon]:p-0!"
                        asChild
                    >
                        {#snippet children(props)}
                            <a
                                {...props}
                                href={toUrl(item.href)}
                                target="_blank"
                                rel="noopener noreferrer"
                                class={props.class}
                                onclick={handleNavigate}
                            >
                                {#if item.icon}
                                    <item.icon class="size-4 shrink-0" />
                                {/if}
                                <span
                                    class="group-data-[collapsible=icon]:hidden"
                                >
                                    {item.title}
                                </span>
                            </a>
                        {/snippet}
                    </SidebarMenuButton>
                </SidebarMenuItem>
            {/each}
        </SidebarMenu>
    </SidebarGroupContent>
</SidebarGroup>
