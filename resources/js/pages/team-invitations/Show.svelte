<script module lang="ts">
    export const layout = {
        title: 'Team invitation',
        description: 'Review your Testify invitation',
    };
</script>

<script lang="ts">
    import { Form, Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { accept } from '@/routes/team-invitations';

    type Invitation = {
        token: string;
        email: string;
        teamName: string | null;
        inviterName: string | null;
        expiresAt: string | null;
        acceptedAt: string | null;
        isPending: boolean;
        hasExistingAccount: boolean;
        canAcceptAsCurrentUser: boolean;
        loginRedirect: string;
        registerRedirect: string;
    };

    let {
        invitation,
    }: {
        invitation: Invitation;
    } = $props();

    const currentUserEmail = $derived(
        String(page.props.auth?.user?.email ?? ''),
    );
</script>

<AppHead title="Team Invitation" />

<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
        <p class="text-sm font-semibold text-slate-500">Invitation</p>
        <h2 class="mt-2 text-2xl font-bold text-slate-900">
            Join {invitation.teamName ?? 'a Testify team'}
        </h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">
            {invitation.inviterName ?? 'A teammate'} invited
            <strong>{invitation.email}</strong> to collaborate in Testify.
        </p>
    </div>

    {#if invitation.isPending}
        {#if invitation.canAcceptAsCurrentUser}
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-sm leading-6 text-emerald-800">
                    You are signed in as the invited email address. Accept the
                    invitation to unlock the shared projects in your workspace.
                </p>
                <Form
                    action={accept.url(invitation)}
                    method="post"
                    class="mt-4"
                >
                    {#snippet children({ processing })}
                        <Button type="submit" disabled={processing}>
                            Accept invitation
                        </Button>
                    {/snippet}
                </Form>
            </div>
        {:else if invitation.hasExistingAccount}
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-sm leading-6 text-amber-800">
                    This email already has a Testify account.
                    {#if currentUserEmail}
                        You are currently signed in as
                        <strong>{currentUserEmail}</strong>, so switch accounts
                        first.
                    {/if}
                </p>
                <div class="mt-4">
                    <Button asChild>
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href={invitation.loginRedirect}
                                class={props.class}
                            >
                                Log in with invited email
                            </Link>
                        {/snippet}
                    </Button>
                </div>
            </div>
        {:else}
            <div class="rounded-3xl border border-sky-200 bg-sky-50 p-5">
                <p class="text-sm leading-6 text-sky-800">
                    This invitation is waiting for a new Testify account.
                    Create one with <strong>{invitation.email}</strong> to join
                    the team automatically.
                </p>
                <div class="mt-4">
                    <Button asChild>
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href={invitation.registerRedirect}
                                class={props.class}
                            >
                                Register and join team
                            </Link>
                        {/snippet}
                    </Button>
                </div>
            </div>
        {/if}
    {:else if invitation.acceptedAt}
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-sm leading-6 text-emerald-800">
                This invitation has already been accepted.
            </p>
        </div>
    {:else}
        <div class="rounded-3xl border border-rose-200 bg-rose-50 p-5">
            <p class="text-sm leading-6 text-rose-800">
                This invitation has expired. Ask the team owner to send a new
                invitation.
            </p>
        </div>
    {/if}
</div>
