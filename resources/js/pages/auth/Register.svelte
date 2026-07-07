<script module lang="ts">
    export const layout = {
        title: 'Create your account',
        description: 'Set up your Testify workspace access',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { store } from '@/routes/register';
    import { register as registerInvitation } from '@/routes/team-invitations';

    type TeamInvitation = {
        token: string;
        email: string;
        teamName: string | null;
        inviterName: string | null;
    } | null;

    let {
        email = '',
        teamInvitation = null,
    }: {
        email?: string;
        teamInvitation?: TeamInvitation;
    } = $props();

    const action = $derived(
        teamInvitation
            ? registerInvitation.url(teamInvitation)
            : store.url(),
    );
</script>

<AppHead title="Register" />

<Form action={action} method="post" class="flex flex-col gap-6">
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            {#if teamInvitation}
                <div
                    class="rounded-3xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800"
                >
                    Join <strong>{teamInvitation.teamName ?? 'a Testify team'}</strong>
                    invited by
                    <strong>{teamInvitation.inviterName ?? 'your teammate'}</strong>.
                </div>
            {/if}

            <div class="grid gap-2.5">
                <Label for="name" class="text-sm font-semibold">Full name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autocomplete="name"
                    class="h-12 rounded-2xl border-slate-200 bg-white px-4 text-base shadow-none focus-visible:ring-[3px] dark:border-white/10 dark:bg-slate-900"
                />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2.5">
                <Label for="email" class="text-sm font-semibold">
                    Email address
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    value={teamInvitation?.email ?? email}
                    readonly={teamInvitation !== null}
                    class="h-12 rounded-2xl border-slate-200 bg-white px-4 text-base shadow-none focus-visible:ring-[3px] dark:border-white/10 dark:bg-slate-900"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2.5">
                <Label for="password" class="text-sm font-semibold">
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="h-12 rounded-2xl border-slate-200 bg-white px-4 text-base shadow-none focus-visible:ring-[3px] dark:border-white/10 dark:bg-slate-900"
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2.5">
                <Label for="password_confirmation" class="text-sm font-semibold">
                    Confirm password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="h-12 rounded-2xl border-slate-200 bg-white px-4 text-base shadow-none focus-visible:ring-[3px] dark:border-white/10 dark:bg-slate-900"
                />
            </div>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-2xl text-base font-semibold shadow-lg shadow-slate-950/10"
                disabled={processing}
            >
                {#if processing}<Spinner />{/if}
                {teamInvitation ? 'Create account and join team' : 'Create account'}
            </Button>

            <p class="text-center text-sm leading-6 text-muted-foreground">
                Already have an account?
                <TextLink href={login()}>
                    Log in
                </TextLink>
            </p>
        </div>
    {/snippet}
</Form>
