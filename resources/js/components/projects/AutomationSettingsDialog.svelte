<script lang="ts">
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import {
        fetchAutomationSettings,
        updateAutomationSettings,
    } from '@/lib/api/automation';
    import type {
        AutomationEnvironment,
        AutomationSettings,
    } from '@/lib/api/automation';

    type ProjectSummary = { project_id: number; project_name: string };
    type ProfileForm = {
        rowId: string;
        environment: AutomationEnvironment;
        name: string;
        key: string;
        fieldsText: string;
    };

    let {
        project,
        onclose,
    }: {
        project: ProjectSummary | null;
        onclose: () => void;
    } = $props();

    let settings = $state<AutomationSettings | null>(null);
    let profiles = $state<ProfileForm[]>([]);
    let loading = $state(false);
    let saving = $state(false);
    let loadedProjectId = $state<number | null>(null);
    let nextProfileRowId = 0;

    const createProfileRowId = (): string => {
        nextProfileRowId += 1;

        return `credential-profile-${nextProfileRowId}`;
    };

    const load = async (current: ProjectSummary): Promise<void> => {
        loading = true;

        try {
            settings = await fetchAutomationSettings(current.project_id);
            profiles = settings.credential_profiles.map((profile) => ({
                rowId: createProfileRowId(),
                environment: profile.environment,
                name: profile.name,
                key: profile.key,
                fieldsText: profile.fields.join(', '),
            }));
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load automation settings.',
            );
        } finally {
            loading = false;
        }
    };

    const addProfile = (): void => {
        profiles = [
            ...profiles,
            {
                rowId: createProfileRowId(),
                environment: 'SIT',
                name: '',
                key: '',
                fieldsText: 'email, password',
            },
        ];
    };

    const removeProfile = (index: number): void => {
        profiles = profiles.filter((_, profileIndex) => profileIndex !== index);
    };

    const save = async (): Promise<void> => {
        if (!project || !settings) {
            return;
        }

        saving = true;

        try {
            settings = await updateAutomationSettings(project.project_id, {
                ...settings,
                credential_profiles: profiles.map((profile) => ({
                    environment: profile.environment,
                    name: profile.name.trim(),
                    key: profile.key.trim(),
                    fields: profile.fieldsText
                        .split(',')
                        .map((field) => field.trim())
                        .filter(Boolean),
                })),
            });
            toast.success('Automation settings saved.');
            onclose();
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to save automation settings.',
            );
        } finally {
            saving = false;
        }
    };

    $effect(() => {
        if (project && loadedProjectId !== project.project_id) {
            loadedProjectId = project.project_id;
            void load(project);
        }

        if (!project) {
            loadedProjectId = null;
            settings = null;
            profiles = [];
        }
    });
</script>

<Dialog open={Boolean(project)} onOpenChange={(open) => !open && onclose()}>
    <DialogContent
        class="max-h-[90vh] max-w-4xl overflow-y-auto border-slate-200 bg-white text-[#071a2f]"
    >
        <DialogTitle>Automation Settings</DialogTitle>
        <DialogDescription>
            Configure environment origins and credential profile metadata for {project?.project_name ??
                'this project'}. Secret values remain in GitHub Environments.
        </DialogDescription>

        {#if loading}
            <div class="flex min-h-52 items-center justify-center">
                <Spinner />
            </div>
        {:else if settings}
            <div class="mt-4 grid gap-6">
                <section>
                    <h3 class="font-semibold">Environment URLs</h3>
                    <div class="mt-3 grid gap-3">
                        {#each settings.environments as environment, index (environment.environment)}
                            <div
                                class="grid gap-3 rounded-xl border border-slate-200 p-3 md:grid-cols-[9rem_1fr_auto] md:items-center"
                            >
                                <label
                                    for={`automation-environment-${index}-url`}
                                    class="text-sm font-semibold"
                                    >{environment.environment}</label
                                >
                                <Input
                                    id={`automation-environment-${index}-url`}
                                    type="url"
                                    placeholder="https://sit.example.com"
                                    bind:value={environment.base_url}
                                />
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        id={`automation-environment-${index}-enabled`}
                                        type="checkbox"
                                        bind:checked={environment.enabled}
                                    />
                                    Enabled
                                </label>
                            </div>
                        {/each}
                    </div>
                </section>

                <section>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Credential Profiles</h3>
                            <p class="text-sm text-slate-500">
                                Names and field declarations only. Never enter
                                secret values here.
                            </p>
                        </div>
                        <Button
                            id="add-automation-credential-profile"
                            type="button"
                            variant="outline"
                            onclick={addProfile}
                        >
                            <Plus class="size-4" /> Add Profile
                        </Button>
                    </div>
                    <div class="mt-3 grid gap-3">
                        {#each profiles as profile, index (profile.rowId)}
                            <div
                                class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-2"
                            >
                                <div class="grid gap-1">
                                    <label
                                        for={`credential-profile-${index}-environment`}
                                        class="text-sm font-medium"
                                        >Environment</label
                                    >
                                    <select
                                        id={`credential-profile-${index}-environment`}
                                        bind:value={profile.environment}
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                    >
                                        {#each settings.environments as item (item.environment)}<option
                                                value={item.environment}
                                                >{item.environment}</option
                                            >{/each}
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <label
                                        for={`credential-profile-${index}-name`}
                                        class="text-sm font-medium"
                                        >Display name</label
                                    >
                                    <Input
                                        id={`credential-profile-${index}-name`}
                                        bind:value={profile.name}
                                        placeholder="Default test user"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <label
                                        for={`credential-profile-${index}-key`}
                                        class="text-sm font-medium"
                                        >Secret profile key</label
                                    >
                                    <Input
                                        id={`credential-profile-${index}-key`}
                                        bind:value={profile.key}
                                        placeholder="default_user"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <label
                                        for={`credential-profile-${index}-fields`}
                                        class="text-sm font-medium"
                                        >Fields, comma separated</label
                                    >
                                    <Input
                                        id={`credential-profile-${index}-fields`}
                                        bind:value={profile.fieldsText}
                                        placeholder="email, password"
                                    />
                                </div>
                                <div class="md:col-span-2 md:text-right">
                                    <Button
                                        id={`remove-credential-profile-${index}`}
                                        type="button"
                                        variant="ghost"
                                        class="text-red-600"
                                        onclick={() => removeProfile(index)}
                                    >
                                        <Trash2 class="size-4" /> Remove
                                    </Button>
                                </div>
                            </div>
                        {:else}
                            <p
                                class="rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500"
                            >
                                No credential profiles configured.
                            </p>
                        {/each}
                    </div>
                </section>
            </div>
        {/if}

        <DialogFooter class="mt-6">
            <DialogClose asChild>
                {#snippet children(closeProps)}
                    <Button
                        type="button"
                        variant="outline"
                        onclick={closeProps.onClick}>Cancel</Button
                    >
                {/snippet}
            </DialogClose>
            <Button
                id="save-automation-settings"
                type="button"
                disabled={saving || loading || !settings}
                onclick={save}
            >
                {#if saving}<Spinner />{/if} Save Settings
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
