<script module lang="ts">
    export const layout = {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { register } from '@/routes';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';

    let {
        status = '',
        canResetPassword,
        email = '',
        redirect = '',
    }: {
        status?: string;
        canResetPassword: boolean;
        email?: string;
        redirect?: string;
    } = $props();
</script>

<AppHead title="Log in" />

{#if status}
    <div
        class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300"
    >
        {status}
    </div>
{/if}

<Form
    action={store.url()}
    method="post"
    resetOnSuccess={['password']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div
                class="rounded-3xl border border-slate-200/70 bg-slate-50/75 p-4 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-950 text-white dark:bg-white dark:text-slate-950"
                    >
                        <ShieldCheck class="size-4" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm font-semibold">Secure login</p>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Use your email and password to access your projects,
                            notes, and workspace activity.
                        </p>
                    </div>
                </div>
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
                    placeholder="email@example.com"
                    value={email}
                    class="h-12 rounded-2xl border-slate-200 bg-white px-4 text-base shadow-none focus-visible:ring-[3px] dark:border-white/10 dark:bg-slate-900"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2.5">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-sm font-semibold">
                        Password
                    </Label>
                    {#if canResetPassword}
                        <TextLink href={request()} class="text-sm font-medium">
                            Forgot your password?
                        </TextLink>
                    {/if}
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                    class="h-12 rounded-2xl border-slate-200 bg-white px-4 text-base shadow-none focus-visible:ring-[3px] dark:border-white/10 dark:bg-slate-900"
                />
                <InputError message={errors.password} />
            </div>

            <div
                class="flex flex-col gap-4 rounded-3xl border border-dashed border-slate-200 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5"
            >
                <Label
                    for="remember"
                    class="flex items-center gap-3 text-sm font-medium"
                >
                    <Checkbox id="remember" name="remember" />
                    <span>Remember me</span>
                </Label>

                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Check class="size-4 text-sky-600 dark:text-sky-300" />
                    <span
                        >Trusted device sign-in for quicker access next time.</span
                    >
                </div>
            </div>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-2xl text-base font-semibold shadow-lg shadow-slate-950/10"
                disabled={processing}
                data-test="login-button"
            >
                {#if processing}<Spinner />{/if}
                Log in
            </Button>

            {#if redirect}
                <input type="hidden" name="redirect" value={redirect} />
            {/if}

            <p class="text-center text-sm leading-6 text-muted-foreground">
                Protected with session security and rate limiting by default.
            </p>

            <p class="text-center text-sm leading-6 text-muted-foreground">
                New to Testify?
                <TextLink
                    href={register({
                        query: {
                            ...(email ? { email } : {}),
                        },
                    })}
                >
                    Create an account
                </TextLink>
            </p>
        </div>
    {/snippet}
</Form>
