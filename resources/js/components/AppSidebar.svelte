<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import BookOpen from 'lucide-svelte/icons/book-open';
    import Bug from 'lucide-svelte/icons/bug';
    import CircleHelp from 'lucide-svelte/icons/circle-help';
    import CirclePlay from 'lucide-svelte/icons/circle-play';
    import Database from 'lucide-svelte/icons/database';
    import FolderGit2 from 'lucide-svelte/icons/folder-git-2';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import ListChecks from 'lucide-svelte/icons/list-checks';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as issuesIndex } from '@/routes/issues';
    import { index as projectsIndex } from '@/routes/projects';
    import { index as reportsIndex } from '@/routes/reports';
    import { index as testExecutionsIndex } from '@/routes/test-executions';
    import { index as testPlansIndex } from '@/routes/test-plans';
    import { index as testRepositoriesIndex } from '@/routes/test-repositories';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const appName = $derived(String(page.props.name ?? 'Testify'));

    const mainNavItems: NavItem[] = [
        {
            title: 'Projects',
            href: projectsIndex(),
            icon: FolderGit2,
        },
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Test Plans',
            href: testPlansIndex(),
            icon: ListChecks,
        },
        {
            title: 'Test Repositories',
            href: testRepositoriesIndex(),
            icon: Database,
        },
        {
            title: 'Test Executions',
            href: testExecutionsIndex(),
            icon: CirclePlay,
        },
        {
            title: 'Issues',
            href: issuesIndex(),
            icon: Bug,
        },
        {
            title: 'Reports',
            href: reportsIndex(),
            icon: BarChart3,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Help Center',
            href: 'https://laravel.com/docs',
            icon: CircleHelp,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#svelte',
            icon: BookOpen,
        },
    ];
</script>

<Sidebar
    collapsible="icon"
    variant="inset"
    class="border-[#0a2747] shadow-[10px_0_28px_rgba(2,6,23,0.14)]"
    style="background: linear-gradient(180deg, #0c2d53 0%, #061b33 100%); color: white; --sidebar-background: #061b33; --sidebar-foreground: #ffffff; --sidebar-primary: #ffffff; --sidebar-primary-foreground: #061b33; --sidebar-accent: rgba(255, 255, 255, 0.08); --sidebar-accent-foreground: #ffffff; --sidebar-border: rgba(255, 255, 255, 0.1); --sidebar-ring: rgba(255, 255, 255, 0.48);"
>
    <SidebarHeader class="gap-0 px-3 pt-4 pb-2">
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={`${props.class} h-14 rounded-xl px-3 text-white transition-colors duration-150 hover:bg-white/[0.06] hover:text-white focus-visible:ring-2 focus-visible:ring-sky-300/70 focus-visible:ring-offset-2 focus-visible:ring-offset-[#082341]`}
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/[0.09] ring-1 ring-white/10"
                            >
                                <AppLogoIcon
                                    variant="monochrome"
                                    class="size-5.5 text-white"
                                />
                            </span>
                            <span
                                class="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden"
                            >
                                <span
                                    class="truncate text-base leading-tight font-semibold text-white"
                                >
                                    {appName}
                                </span>
                                <span
                                    class="truncate text-[0.65rem] tracking-[0.17em] text-white/50 uppercase"
                                >
                                    QA Workspace
                                </span>
                            </span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter class="gap-2 px-3 pt-2 pb-3">
        <NavFooter items={footerNavItems} class="pt-0" />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
