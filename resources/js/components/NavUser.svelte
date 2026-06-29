<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import ChevronsUpDown from 'lucide-svelte/icons/chevrons-up-down';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import {
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
        useSidebar,
    } from '@/components/ui/sidebar';
    import UserInfo from '@/components/UserInfo.svelte';
    import UserMenuContent from '@/components/UserMenuContent.svelte';

    const user = $derived(page.props.auth.user);
    const { isMobile, state: sidebarState } = useSidebar();
</script>

{#if user}
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu class="w-full">
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <SidebarMenuButton
                            size="lg"
                            class="h-12 rounded-xl bg-white/[0.06] px-2 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.05)] transition-[background-color,box-shadow] duration-150 hover:bg-white/[0.1] hover:text-white focus-visible:ring-2 focus-visible:ring-sky-300/70 focus-visible:ring-offset-2 focus-visible:ring-offset-[#082341] data-[state=open]:bg-white/[0.1] data-[state=open]:text-white group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:rounded-xl group-data-[collapsible=icon]:p-1!"
                            data-test="sidebar-menu-button"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                            data-state={props['data-state']}
                        >
                            <UserInfo
                                {user}
                                detailsClass="group-data-[collapsible=icon]:hidden"
                            />
                            <ChevronsUpDown
                                class="ml-auto size-4 group-data-[collapsible=icon]:hidden"
                            />
                        </SidebarMenuButton>
                    {/snippet}
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-full min-w-0 rounded-lg"
                    side={$sidebarState === 'collapsed' && !$isMobile
                        ? 'left'
                        : 'top'}
                    align="end"
                    sideOffset={4}
                >
                    <UserMenuContent {user} />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
{/if}
