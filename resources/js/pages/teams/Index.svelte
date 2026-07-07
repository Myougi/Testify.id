<script module lang="ts">
    import { index as teamsIndexRoute } from '@/routes/teams';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Teams',
                href: teamsIndexRoute(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import FolderGit2 from 'lucide-svelte/icons/folder-git-2';
    import MailPlus from 'lucide-svelte/icons/mail-plus';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import UserMinus from 'lucide-svelte/icons/user-minus';
    import Users from 'lucide-svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import teams, {
        destroy as destroyTeam,
        store as storeTeam,
        update as updateTeam,
    } from '@/routes/teams';

    type Member = {
        id: number;
        name: string;
        email: string;
    };

    type Invitation = {
        id: number;
        email: string;
        expires_at: string | null;
    };

    type SharedProject = {
        id?: number;
        project_id: number;
        project_name: string;
        projectTeamAccessId?: number;
    };

    type Team = {
        id: number;
        name: string;
        members: Member[];
        pendingInvitations: Invitation[];
        projects: SharedProject[];
    };

    type ShareableProject = {
        project_id: number;
        project_name: string;
    };

    let {
        teams: teamList,
        shareableProjects,
    }: {
        teams: Team[];
        shareableProjects: ShareableProject[];
    } = $props();

    let createOpen = $state(false);
    let editingTeam = $state<Team | null>(null);
    let deletingTeam = $state<Team | null>(null);

    const dateFormatter = new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    function formatInviteExpiry(value: string | null): string {
        if (!value) {
            return 'Expires soon';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return 'Expires soon';
        }

        return `Expires ${dateFormatter.format(date)}`;
    }
</script>

<AppHead title="Teams" />

<div class="space-y-6 text-[#071a2f]">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1">
            <h1 class="text-2xl font-semibold tracking-normal text-[#071a2f]">
                Teams
            </h1>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">
                Manage reusable teams, invite members by email, and share owned
                projects with the right collaborators.
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
                        class="h-10 w-full shrink-0 bg-[#002b57] text-white hover:bg-[#003c78] sm:w-auto"
                    >
                        <Plus class="size-4" />
                        Create Team
                    </Button>
                {/snippet}
            </DialogTrigger>
            <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
                <DialogTitle>Create Team</DialogTitle>
                <Form
                    action={storeTeam.url()}
                    method="post"
                    onSuccess={() => (createOpen = false)}
                    class="mt-5 grid gap-4"
                >
                    {#snippet children({ errors, processing })}
                        <div class="grid gap-2">
                            <Label for="name">Team name</Label>
                            <Input id="name" name="name" required />
                            <InputError message={errors.name} />
                        </div>
                        <DialogFooter>
                            <DialogClose asChild>
                                {#snippet children(closeProps)}
                                    <Button
                                        onclick={closeProps.onClick}
                                        variant="outline"
                                    >
                                        Cancel
                                    </Button>
                                {/snippet}
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                {#if processing}<Spinner />{/if}
                                Create Team
                            </Button>
                        </DialogFooter>
                    {/snippet}
                </Form>
            </DialogContent>
        </Dialog>
    </div>

    {#if teamList.length === 0}
        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
            <div class="mx-auto flex size-10 items-center justify-center rounded-md bg-white text-[#002b57] shadow-sm">
                <Users class="size-5" />
            </div>
            <h2 class="mt-4 text-base font-semibold text-[#071a2f]">
                No teams yet
            </h2>
            <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-600">
                Create a team to invite collaborators and share project access
                without changing project ownership.
            </p>
            <Button
                type="button"
                class="mt-5 bg-[#002b57] text-white hover:bg-[#003c78]"
                onclick={() => (createOpen = true)}
            >
                <Plus class="size-4" />
                Create Team
            </Button>
        </div>
    {:else}
        <div class="space-y-4">
            {#each teamList as team (team.id)}
                <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50/80 p-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-[#e7eff8] text-[#002b57]">
                                <Users class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-[#071a2f]">
                                    {team.name}
                                </h2>
                                <p class="mt-0.5 text-sm text-slate-600">
                                    {team.members.length} members
                                    <span class="mx-1 text-slate-300">/</span>
                                    {team.projects.length} shared projects
                                </p>
                                {#if team.pendingInvitations.length > 0}
                                    <Badge class="mt-2 bg-sky-100 text-sky-700">
                                        {team.pendingInvitations.length} pending invites
                                    </Badge>
                                {/if}
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-9"
                                onclick={() => (editingTeam = team)}
                            >
                                <Pencil class="size-4" />
                                Edit
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                class="h-9"
                                onclick={() => (deletingTeam = team)}
                            >
                                <Trash2 class="size-4" />
                                Delete
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-6 p-4 xl:grid-cols-2">
                        <section class="min-w-0 space-y-4">
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <MailPlus class="size-4 shrink-0 text-[#002b57]" />
                                    <h3 class="text-sm font-semibold text-[#071a2f]">
                                        Invite member
                                    </h3>
                                </div>
                                <Form
                                    action={teams.members.store.url(team)}
                                    method="post"
                                    class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                                >
                                    {#snippet children({ errors, processing })}
                                        <div class="min-w-0">
                                            <Input
                                                name="email"
                                                type="email"
                                                placeholder="teammate@example.com"
                                                required
                                            />
                                            <InputError
                                                class="mt-2"
                                                message={errors.email}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            class="h-9 shrink-0"
                                        >
                                            {#if processing}<Spinner />{/if}
                                            Add by email
                                        </Button>
                                    {/snippet}
                                </Form>
                            </div>

                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                    Members
                                </p>
                                {#if team.members.length > 0}
                                    <div class="space-y-2">
                                        {#each team.members as member (member.id)}
                                            <div class="flex min-w-0 items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2.5">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-[#071a2f]">
                                                        {member.name}
                                                    </p>
                                                    <p class="truncate text-sm text-slate-500">
                                                        {member.email}
                                                    </p>
                                                </div>
                                                <Form
                                                    action={teams.members.destroy.url({
                                                        team,
                                                        teamMember: member,
                                                    })}
                                                    method="post"
                                                    class="shrink-0"
                                                >
                                                    {#snippet children({ processing })}
                                                        <input
                                                            type="hidden"
                                                            name="_method"
                                                            value="DELETE"
                                                        />
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={processing}
                                                            aria-label={`Remove ${member.name}`}
                                                        >
                                                            <UserMinus class="size-4" />
                                                        </Button>
                                                    {/snippet}
                                                </Form>
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <p class="rounded-md border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">
                                        No members yet.
                                    </p>
                                {/if}
                            </div>

                            {#if team.pendingInvitations.length > 0}
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        Pending invitations
                                    </p>
                                    <div class="space-y-2">
                                        {#each team.pendingInvitations as invitation (invitation.id)}
                                            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5">
                                                <p class="truncate text-sm font-semibold text-amber-950">
                                                    {invitation.email}
                                                </p>
                                                <p class="mt-0.5 text-xs text-amber-700">
                                                    {formatInviteExpiry(invitation.expires_at)}
                                                </p>
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </section>

                        <section class="min-w-0 space-y-4">
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <FolderGit2 class="size-4 shrink-0 text-[#002b57]" />
                                    <h3 class="text-sm font-semibold text-[#071a2f]">
                                        Share project
                                    </h3>
                                </div>
                                <Form
                                    action={teams.projectAccess.store.url(team)}
                                    method="post"
                                    class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                                >
                                    {#snippet children({ errors, processing })}
                                        <div class="min-w-0">
                                            <select
                                                name="project_id"
                                                required
                                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-2 focus:ring-[#002b57]/15"
                                            >
                                                <option value="">Select a project</option>
                                                {#each shareableProjects as project (project.project_id)}
                                                    <option value={project.project_id}>
                                                        {project.project_name}
                                                    </option>
                                                {/each}
                                            </select>
                                            <InputError
                                                class="mt-2"
                                                message={errors.project_id}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            class="h-9 shrink-0"
                                        >
                                            {#if processing}<Spinner />{/if}
                                            Share project
                                        </Button>
                                    {/snippet}
                                </Form>
                            </div>

                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                    Shared projects
                                </p>
                                {#if team.projects.length > 0}
                                    <div class="space-y-2">
                                        {#each team.projects as project (project.project_id)}
                                            <div class="flex min-w-0 items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2.5">
                                                <p class="min-w-0 truncate text-sm font-semibold text-[#071a2f]">
                                                    {project.project_name}
                                                </p>
                                                {#if project.projectTeamAccessId}
                                                    <Form
                                                        action={teams.projectAccess.destroy.url({
                                                            team,
                                                            projectTeamAccess:
                                                                project.projectTeamAccessId,
                                                        })}
                                                        method="post"
                                                        class="shrink-0"
                                                    >
                                                        {#snippet children({ processing })}
                                                            <input
                                                                type="hidden"
                                                                name="_method"
                                                                value="DELETE"
                                                            />
                                                            <Button
                                                                type="submit"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={processing}
                                                                aria-label={`Remove ${project.project_name}`}
                                                            >
                                                                <Trash2 class="size-4" />
                                                            </Button>
                                                        {/snippet}
                                                    </Form>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <p class="rounded-md border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">
                                        No projects shared with this team yet.
                                    </p>
                                {/if}
                            </div>
                        </section>
                    </div>
                </article>
            {/each}
        </div>
    {/if}
</div>

<Dialog
    open={Boolean(editingTeam)}
    onOpenChange={(open) => !open && (editingTeam = null)}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Edit Team</DialogTitle>
        {#if editingTeam}
            {@const team = editingTeam}
            <Form
                action={updateTeam.url(team)}
                method="post"
                onSuccess={() => (editingTeam = null)}
                class="mt-5 grid gap-4"
            >
                {#snippet children({ errors, processing })}
                    <input type="hidden" name="_method" value="PUT" />
                    <div class="grid gap-2">
                        <Label for="edit_team_name">Team name</Label>
                        <Input
                            id="edit_team_name"
                            name="name"
                            value={team.name}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            {#snippet children(closeProps)}
                                <Button onclick={closeProps.onClick} variant="outline">
                                    Cancel
                                </Button>
                            {/snippet}
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {#if processing}<Spinner />{/if}
                            Save Changes
                        </Button>
                    </DialogFooter>
                {/snippet}
            </Form>
        {/if}
    </DialogContent>
</Dialog>

<Dialog
    open={Boolean(deletingTeam)}
    onOpenChange={(open) => !open && (deletingTeam = null)}
>
    <DialogContent class="border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Delete Team</DialogTitle>
        {#if deletingTeam}
            {@const team = deletingTeam}
            <Form
                action={destroyTeam.url(team)}
                method="post"
                onSuccess={() => (deletingTeam = null)}
                class="mt-5"
            >
                {#snippet children({ processing })}
                    <input type="hidden" name="_method" value="DELETE" />
                    <p class="text-sm text-slate-600">
                        Delete <strong>{team.name}</strong> and remove all of
                        its member and project access records?
                    </p>
                    <div class="mt-5">
                        <DialogFooter>
                            <DialogClose asChild>
                                {#snippet children(closeProps)}
                                    <Button onclick={closeProps.onClick} variant="outline">
                                        Cancel
                                    </Button>
                                {/snippet}
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {#if processing}<Spinner />{/if}
                                Delete Team
                            </Button>
                        </DialogFooter>
                    </div>
                {/snippet}
            </Form>
        {/if}
    </DialogContent>
</Dialog>
