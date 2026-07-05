<script module lang="ts">
    import { index as projectsIndexRoute } from '@/routes/projects';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Projects',
                href: projectsIndexRoute(),
            },
            {
                title: 'All Projects',
                href: projectsIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import Archive from 'lucide-svelte/icons/archive';
    import Building2 from 'lucide-svelte/icons/building-2';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import CirclePlus from 'lucide-svelte/icons/circle-plus';
    import Cloud from 'lucide-svelte/icons/cloud';
    import EllipsisVertical from 'lucide-svelte/icons/ellipsis-vertical';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import FileText from 'lucide-svelte/icons/file-text';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import {
        destroy,
        index as projectsIndex,
        store,
        update,
    } from '@/routes/projects';

    type Project = {
        project_id: number;
        project_name: string;
        project_description: string | null;
        project_url: string | null;
        test_cases_count: number;
        test_plans_count: number;
        active_test_plans_count: number;
        completed_test_plans_count: number;
        execution_progress: number;
        open_issues_count: number;
        created_at: string;
    };

    type Stats = {
        totalProjects: number;
        activeProjects: number;
        inProgressProjects: number;
        archivedProjects: number;
    };

    let {
        projects,
        status,
        stats,
    }: {
        projects: Project[];
        status: string;
        stats: Stats;
    } = $props();

    let createOpen = $state(false);
    let editingProject = $state<Project | null>(null);
    let deletingProject = $state<Project | null>(null);
    const cardIcons = [Building2, FileText, Cloud, Archive];

    const statCards = $derived([
        {
            title: 'Total Project',
            value: String(stats.totalProjects),
            detail: 'All projects in the workspace',
            detailClass: 'text-slate-500',
            href: projectsIndex(),
        },
        {
            title: 'Active',
            value: String(stats.activeProjects),
            detail: 'Status: Active',
            detailClass: 'text-emerald-600',
            href: projectsIndex({ query: { status: 'active' } }),
        },
        {
            title: 'In Progress',
            value: String(stats.inProgressProjects),
            detail: 'Status: In Progress',
            detailClass: 'text-sky-600',
            href: projectsIndex({ query: { status: 'in-progress' } }),
        },
        {
            title: 'Archived',
            value: String(stats.archivedProjects),
            detail: 'Status: Archived',
            detailClass: 'text-slate-500',
            href: projectsIndex({ query: { status: 'archived' } }),
        },
    ]);
</script>

<AppHead title="Projects" />

<div class="min-h-screen bg-[#f4f7fa] px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs font-medium">
                    <Link
                        href={projectsIndex().url}
                        class="text-slate-500 hover:text-[#002b57]"
                    >
                        Projects
                    </Link>
                    <span class="text-slate-400">&gt;</span>
                    <span>All Projects</span>
                </div>
                <h1 class="text-3xl font-bold tracking-normal text-[#071a2f]">
                    Active Projects
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Manage and monitor your enterprise test suites across all
                    development environments.
                </p>
            </div>

            <Dialog bind:open={createOpen}>
                <DialogTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            onclick={() => {
                                if (typeof props.onClick === 'function') {
                                    props.onClick();
                                }
                            }}
                            aria-expanded={Boolean(props['aria-expanded'])}
                            class="h-11 bg-[#002b57] px-5 text-white shadow-sm hover:bg-[#003c78]"
                        >
                            <Plus class="size-4" />
                            Create New Project
                        </Button>
                    {/snippet}
                </DialogTrigger>
                <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
                    <DialogTitle>Create New Project</DialogTitle>
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        onSuccess={() => (createOpen = false)}
                        class="mt-5 grid gap-4"
                    >
                        {#snippet children({ errors, processing })}
                            <div class="grid gap-2">
                                <Label for="project_name">Project name</Label>
                                <Input
                                    id="project_name"
                                    name="project_name"
                                    required
                                    class="border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                                />
                                <InputError message={errors.project_name} />
                            </div>
                            <div class="grid gap-2">
                                <Label for="project_description"
                                    >Description</Label
                                >
                                <textarea
                                    id="project_description"
                                    name="project_description"
                                    rows="4"
                                    class="flex min-h-24 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus-visible:border-[#002b57] focus-visible:ring-[3px] focus-visible:ring-[#002b57]/20 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                ></textarea>
                                <InputError
                                    message={errors.project_description}
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="project_url">Project URL</Label>
                                <Input
                                    id="project_url"
                                    name="project_url"
                                    type="url"
                                    placeholder="https://example.com"
                                    class="border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                                />
                                <InputError message={errors.project_url} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    {#snippet children(closeProps)}
                                        <Button
                                            onclick={closeProps.onClick}
                                            variant="outline">Cancel</Button
                                        >
                                    {/snippet}
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {#if processing}<Spinner />{/if}
                                    Create Project
                                </Button>
                            </DialogFooter>
                        {/snippet}
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {#each statCards as stat (stat.title)}
                <Link
                    href={stat.href}
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-normal text-slate-500"
                    >
                        {stat.title}
                    </p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-bold">{stat.value}</span>
                        <span
                            class={`text-xs font-semibold ${stat.detailClass}`}
                            >{stat.detail}</span
                        >
                    </div>
                </Link>
            {/each}
        </div>

        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-600">
                {#if status === 'all'}
                    Showing all projects
                {:else if status === 'active'}
                    Showing active projects
                {:else if status === 'in-progress'}
                    Showing projects in progress
                {:else if status === 'archived'}
                    Showing archived projects
                {/if}
            </p>
            <Link
                href={projectsIndex()}
                class="text-sm font-semibold text-[#002b57] hover:underline"
            >
                Clear filter
            </Link>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            {#each projects as project, projectIndex (project.project_id)}
                {@const Icon = cardIcons[projectIndex % cardIcons.length]}
                {@const progress = project.execution_progress}
                {@const issueCount = project.open_issues_count ?? 0}
                <article
                    class="rounded-[1.25rem] border border-slate-200/90 bg-white shadow-[0_12px_34px_rgba(15,23,42,0.08)] transition-shadow hover:shadow-[0_18px_42px_rgba(15,23,42,0.12)]"
                >
                    <div
                        class="relative overflow-hidden rounded-t-[1.25rem] bg-[#082846]"
                    >
                        <div class="relative h-32 overflow-hidden">
                            <div
                                class="absolute inset-0 bg-[linear-gradient(135deg,rgba(9,37,63,0.95),rgba(0,43,87,0.55)),repeating-linear-gradient(90deg,rgba(255,255,255,0.13)_0_1px,transparent_1px_18px),repeating-linear-gradient(0deg,rgba(255,255,255,0.08)_0_1px,transparent_1px_14px)]"
                            ></div>
                            <div
                                class="absolute bottom-4 left-4 flex items-center gap-3 text-white"
                            >
                                <span
                                    class="flex size-10 items-center justify-center rounded-md bg-blue-100 text-[#002b57]"
                                >
                                    <Icon class="size-5" />
                                </span>
                                <h2 class="text-base font-bold">
                                    {project.project_name}
                                </h2>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 p-5">
                        <p class="min-h-10 text-sm leading-6 text-slate-600">
                            {project.project_description ??
                                'No project description yet.'}
                        </p>

                        {#if project.project_url}
                            <a
                                href={project.project_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-sm font-semibold text-[#002b57] transition-colors hover:border-[#002b57]/30 hover:bg-slate-50 hover:no-underline"
                            >
                                <ExternalLink class="size-4" />
                                Project URL
                            </a>
                        {/if}

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium text-slate-500">
                                    Test Cases
                                </p>
                                <p class="text-xl font-bold">
                                    {project.test_cases_count}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-slate-500">
                                    Active Plans
                                </p>
                                <p class="text-xl font-bold">
                                    {project.active_test_plans_count}
                                </p>
                            </div>
                        </div>

                        <div>
                            <div
                                class="mb-2 flex items-center justify-between text-xs font-medium"
                            >
                                <span class="text-slate-500"
                                    >Recent Execution Progress</span
                                >
                                <span
                                    class={progress < 35
                                        ? 'text-red-600'
                                        : 'text-[#002b57]'}>{progress}%</span
                                >
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-slate-200"
                            >
                                <div
                                    class={progress < 35
                                        ? 'h-full rounded-full bg-red-600'
                                        : 'h-full rounded-full bg-[#002b57]'}
                                    style={`width: ${progress}%`}
                                ></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                asChild
                                class="h-11 flex-1 rounded-2xl bg-[#002b57] text-white shadow-sm hover:bg-[#003c78]"
                            >
                                {#snippet children(props)}
                                    <Link
                                        {...props}
                                        href={`/projects/${project.project_id}/workspace`}
                                        class={props.class}
                                    >
                                        View Project
                                    </Link>
                                {/snippet}
                            </Button>
                            <div class="relative shrink-0">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        {#snippet children(props)}
                                            <Button
                                                {...props}
                                                variant="outline"
                                                size="icon"
                                                class="h-11 w-11 rounded-2xl border-slate-200 bg-white shadow-sm hover:bg-slate-50"
                                                aria-label={`Actions for ${project.project_name}`}
                                            >
                                                <EllipsisVertical
                                                    class="size-4"
                                                />
                                            </Button>
                                        {/snippet}
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        sideOffset={8}
                                        class="w-44 rounded-2xl border-slate-200 p-2 shadow-xl"
                                    >
                                        <DropdownMenuItem
                                            class="gap-2 rounded-xl px-3 py-2"
                                            asChild
                                        >
                                            {#snippet children(menuProps)}
                                                <button
                                                    id={`edit-project-${project.project_id}`}
                                                    type="button"
                                                    class={menuProps.class}
                                                    onclick={() => {
                                                        menuProps.onClick?.();
                                                        editingProject =
                                                            project;
                                                    }}
                                                >
                                                    <Pencil class="size-4" />
                                                    Edit
                                                </button>
                                            {/snippet}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="gap-2 rounded-xl px-3 py-2 text-red-600 hover:text-red-700"
                                            asChild
                                        >
                                            {#snippet children(menuProps)}
                                                <button
                                                    id={`delete-project-${project.project_id}`}
                                                    type="button"
                                                    class={menuProps.class}
                                                    onclick={() => {
                                                        menuProps.onClick?.();
                                                        deletingProject =
                                                            project;
                                                    }}
                                                >
                                                    <Trash2 class="size-4" />
                                                    Delete
                                                </button>
                                            {/snippet}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        {#if issueCount > 0}
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700"
                            >
                                <CheckCircle2 class="size-4" />
                                {issueCount} open {issueCount === 1
                                    ? 'issue'
                                    : 'issues'} tracked
                            </div>
                        {/if}
                    </div>
                </article>
            {/each}

            <Dialog bind:open={createOpen}>
                <DialogTrigger asChild>
                    {#snippet children(props)}
                        <button
                            id="create-project-card"
                            type="button"
                            class="flex min-h-[24rem] flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white/40 p-8 text-center transition hover:border-[#002b57] hover:bg-white"
                            onclick={() => {
                                if (typeof props.onClick === 'function') {
                                    props.onClick();
                                }
                            }}
                            aria-expanded={Boolean(props['aria-expanded'])}
                        >
                            <span
                                class="flex size-14 items-center justify-center rounded-full bg-slate-200 text-[#002b57]"
                            >
                                <CirclePlus class="size-6" />
                            </span>
                            <span class="mt-5 text-base font-bold"
                                >Create New Project</span
                            >
                        </button>
                    {/snippet}
                </DialogTrigger>
            </Dialog>
        </div>
    </div>
</div>

<Dialog
    open={Boolean(editingProject)}
    onOpenChange={(open) => !open && (editingProject = null)}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Edit Project</DialogTitle>
        {#if editingProject}
            {@const project = editingProject}
            {#key project.project_id}
                <Form
                    {...update.form(project)}
                    onSuccess={() => (editingProject = null)}
                    class="mt-5 grid gap-4"
                >
                    {#snippet children({ errors, processing })}
                        <div class="grid gap-2">
                            <Label for="edit_project_name">Project name</Label>
                            <Input
                                id="edit_project_name"
                                name="project_name"
                                required
                                value={project.project_name}
                                class="border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                            />
                            <InputError message={errors.project_name} />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_project_description"
                                >Description</Label
                            >
                            <textarea
                                id="edit_project_description"
                                name="project_description"
                                rows={4}
                                class="flex min-h-24 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus-visible:border-[#002b57] focus-visible:ring-[3px] focus-visible:ring-[#002b57]/20 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >{project.project_description ?? ''}</textarea
                            >
                            <InputError message={errors.project_description} />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_project_url">Project URL</Label>
                            <Input
                                id="edit_project_url"
                                name="project_url"
                                type="url"
                                value={project.project_url ?? ''}
                                class="border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                            />
                            <InputError message={errors.project_url} />
                        </div>
                        <DialogFooter>
                            <DialogClose asChild>
                                {#snippet children(closeProps)}
                                    <Button
                                        onclick={closeProps.onClick}
                                        variant="outline">Cancel</Button
                                    >
                                {/snippet}
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                {#if processing}<Spinner />{/if}
                                Save Changes
                            </Button>
                        </DialogFooter>
                    {/snippet}
                </Form>
            {/key}
        {/if}
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(deletingProject)}
    onOpenChange={(open) => !open && (deletingProject = null)}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Project</DialogTitle>
        <DialogDescription>
            This will remove the project from your workspace. This action cannot
            be undone.
        </DialogDescription>
        {#if deletingProject}
            {@const project = deletingProject}
            <Form
                {...destroy.form(project)}
                onSuccess={() => (deletingProject = null)}
                class="mt-5"
            >
                {#snippet children({ processing })}
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4"
                    >
                        <p class="font-semibold">{project.project_name}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            {project.project_description ??
                                'No project description.'}
                        </p>
                    </div>
                    <div class="mt-5">
                        <DialogFooter>
                            <DialogClose asChild>
                                {#snippet children(closeProps)}
                                    <Button
                                        onclick={closeProps.onClick}
                                        variant="outline">Cancel</Button
                                    >
                                {/snippet}
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {#if processing}<Spinner />{/if}
                                Delete Project
                            </Button>
                        </DialogFooter>
                    </div>
                {/snippet}
            </Form>
        {/if}
    </DialogContent>
</Dialog>
