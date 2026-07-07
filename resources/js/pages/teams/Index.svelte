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
    import { Form, Link } from '@inertiajs/svelte';
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
        index as teamsIndex,
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
</script>

<AppHead title="Teams" />

<div class="min-h-screen bg-[#f4f7fa] px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs font-medium">
                    <Link href={teamsIndex().url} class="text-slate-500 hover:text-[#002b57]">
                        Teams
                    </Link>
                </div>
                <h1 class="text-3xl font-bold tracking-normal text-[#071a2f]">
                    Team Sharing
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
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
                            class="h-11 bg-[#002b57] px-5 text-white shadow-sm hover:bg-[#003c78]"
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
                                        <Button onclick={closeProps.onClick} variant="outline">
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

        <div class="grid gap-6 xl:grid-cols-2">
            {#each teamList as team (team.id)}
                <article class="rounded-[1.25rem] border border-slate-200/90 bg-white p-5 shadow-[0_12px_34px_rgba(15,23,42,0.08)]">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <span class="flex size-11 items-center justify-center rounded-2xl bg-[#e7eff8] text-[#002b57]">
                                    <Users class="size-5" />
                                </span>
                                <div>
                                    <h2 class="text-lg font-bold text-[#071a2f]">
                                        {team.name}
                                    </h2>
                                    <p class="text-sm text-slate-500">
                                        {team.members.length} members
                                        <span class="mx-1">•</span>
                                        {team.projects.length} shared projects
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Badge class="bg-sky-100 text-sky-700">
                                    {team.pendingInvitations.length} pending invites
                                </Badge>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="rounded-xl"
                                onclick={() => (editingTeam = team)}
                            >
                                <Pencil class="size-4" />
                                Edit
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                class="rounded-xl"
                                onclick={() => (deletingTeam = team)}
                            >
                                <Trash2 class="size-4" />
                                Delete
                            </Button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <section class="space-y-3">
                            <div class="flex items-center gap-2">
                                <MailPlus class="size-4 text-[#002b57]" />
                                <h3 class="text-sm font-semibold text-[#071a2f]">
                                    Invite member
                                </h3>
                            </div>
                            <Form
                                action={teams.members.store.url(team)}
                                method="post"
                                class="grid gap-3"
                            >
                                {#snippet children({ errors, processing })}
                                    <Input
                                        name="email"
                                        type="email"
                                        placeholder="teammate@example.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                    <Button type="submit" disabled={processing}>
                                        {#if processing}<Spinner />{/if}
                                        Add by email
                                    </Button>
                                {/snippet}
                            </Form>

                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                    Members
                                </p>
                                {#if team.members.length > 0}
                                    {#each team.members as member (member.id)}
                                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-3 py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-[#071a2f]">
                                                    {member.name}
                                                </p>
                                                <p class="text-sm text-slate-500">
                                                    {member.email}
                                                </p>
                                            </div>
                                            <Form
                                                action={teams.members.destroy.url({
                                                    team,
                                                    teamMember: member,
                                                })}
                                                method="post"
                                            >
                                                {#snippet children({ processing })}
                                                    <input type="hidden" name="_method" value="DELETE" />
                                                    <Button type="submit" variant="outline" disabled={processing}>
                                                        <UserMinus class="size-4" />
                                                    </Button>
                                                {/snippet}
                                            </Form>
                                        </div>
                                    {/each}
                                {:else}
                                    <p class="rounded-2xl border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">
                                        No members yet.
                                    </p>
                                {/if}
                            </div>

                            {#if team.pendingInvitations.length > 0}
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        Pending invitations
                                    </p>
                                    {#each team.pendingInvitations as invitation (invitation.id)}
                                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3">
                                            <p class="text-sm font-semibold text-amber-900">
                                                {invitation.email}
                                            </p>
                                            <p class="text-xs text-amber-700">
                                                Expires {invitation.expires_at ?? 'soon'}
                                            </p>
                                        </div>
                                    {/each}
                                </div>
                            {/if}
                        </section>

                        <section class="space-y-3">
                            <div class="flex items-center gap-2">
                                <FolderGit2 class="size-4 text-[#002b57]" />
                                <h3 class="text-sm font-semibold text-[#071a2f]">
                                    Share project
                                </h3>
                            </div>
                            <Form
                                action={teams.projectAccess.store.url(team)}
                                method="post"
                                class="grid gap-3"
                            >
                                {#snippet children({ errors, processing })}
                                    <select
                                        name="project_id"
                                        required
                                        class="h-11 rounded-2xl border border-slate-200 bg-white px-4 text-sm"
                                    >
                                        <option value="">Select a project</option>
                                        {#each shareableProjects as project (project.project_id)}
                                            <option value={project.project_id}>
                                                {project.project_name}
                                            </option>
                                        {/each}
                                    </select>
                                    <InputError message={errors.project_id} />
                                    <Button type="submit" disabled={processing}>
                                        {#if processing}<Spinner />{/if}
                                        Share project
                                    </Button>
                                {/snippet}
                            </Form>

                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                    Shared projects
                                </p>
                                {#if team.projects.length > 0}
                                    {#each team.projects as project (project.project_id)}
                                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-3 py-3">
                                            <p class="text-sm font-semibold text-[#071a2f]">
                                                {project.project_name}
                                            </p>
                                            {#if project.projectTeamAccessId}
                                                <Form
                                                    action={teams.projectAccess.destroy.url({
                                                        team,
                                                        projectTeamAccess: project.projectTeamAccessId,
                                                    })}
                                                    method="post"
                                                >
                                                    {#snippet children({ processing })}
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <Button type="submit" variant="outline" disabled={processing}>
                                                            <Trash2 class="size-4" />
                                                        </Button>
                                                    {/snippet}
                                                </Form>
                                            {/if}
                                        </div>
                                    {/each}
                                {:else}
                                    <p class="rounded-2xl border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">
                                        No projects shared with this team yet.
                                    </p>
                                {/if}
                            </div>
                        </section>
                    </div>
                </article>
            {/each}
        </div>
    </div>
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
                            <Button type="submit" variant="destructive" disabled={processing}>
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
