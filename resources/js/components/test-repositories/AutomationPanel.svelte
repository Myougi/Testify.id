<script lang="ts">
    import Bot from 'lucide-svelte/icons/bot';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import FlaskConical from 'lucide-svelte/icons/flask-conical';
    import Save from 'lucide-svelte/icons/save';
    import { onMount } from 'svelte';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { Spinner } from '@/components/ui/spinner';
    import {
        approveTestCaseAutomation,
        AutomationApiError,
        fetchTestCaseAutomation,
        generateTestCaseAutomation,
        updateTestCaseAutomation,
        validateTestCaseAutomation,
    } from '@/lib/api/automation';
    import type {
        AutomationCasePayload,
        AutomationDefinition,
        AutomationEnvironment,
    } from '@/lib/api/automation';

    let { testCaseId }: { testCaseId: number } = $props();

    let payload = $state<AutomationCasePayload | null>(null);
    let definitionText = $state('');
    let environment = $state<AutomationEnvironment>('SIT');
    let loading = $state(true);
    let action = $state<'generate' | 'save' | 'validate' | 'approve' | null>(
        null,
    );
    let warnings = $state<string[]>([]);
    let errors = $state<Record<string, string>>({});

    const validationEnvironments =
        (): AutomationCasePayload['settings']['environments'] =>
            payload?.settings.environments ?? [];

    const selectedValidationEnvironment = ():
        | AutomationCasePayload['settings']['environments'][number]
        | null =>
        validationEnvironments().find(
            (item) => item.environment === environment,
        ) ?? null;

    const canValidateSelectedEnvironment = (): boolean => {
        const selected = selectedValidationEnvironment();

        return (
            selected !== null &&
            selected.enabled &&
            selected.base_url !== '' &&
            selected.environment !== 'Production'
        );
    };

    const validationEnvironmentMessage = (): string | null => {
        const selected = selectedValidationEnvironment();

        if (selected === null) {
            return 'Select an environment from Project Automation Settings.';
        }

        if (selected.environment === 'Production') {
            return 'Production cannot be used for validation. Choose a non-production environment.';
        }

        if (!selected.enabled) {
            return `${selected.environment} is disabled in Project Automation Settings.`;
        }

        if (selected.base_url === '') {
            return `${selected.environment} needs a base URL in Project Automation Settings before validation.`;
        }

        return null;
    };

    const definitionStep = (
        definition: AutomationDefinition | null,
        stepNumber: number,
    ): Record<string, unknown> | null =>
        definition?.steps[stepNumber - 1] ?? null;

    const statusLabel = (): string => {
        if (!payload?.automation) {
            return '';
        }

        return payload.automation.status === 'Ready' &&
            payload.automation.approval_source === 'AI Healed'
            ? 'Ready · AI Healed'
            : payload.automation.status;
    };

    const hydrate = (next: AutomationCasePayload): void => {
        payload = next;
        definitionText = next.automation?.definition
            ? JSON.stringify(next.automation.definition, null, 2)
            : '';

        const currentEnvironment = next.settings.environments.find(
            (candidate) => candidate.environment === environment,
        );

        if (currentEnvironment) {
            return;
        }

        const lastValidatedEnvironment = next.automation
            ?.last_validated_environment
            ? next.settings.environments.find(
                  (candidate) =>
                      candidate.environment ===
                      next.automation?.last_validated_environment,
              )
            : null;
        const firstReadyEnvironment = next.settings.environments.find(
            (candidate) =>
                candidate.enabled &&
                candidate.base_url !== '' &&
                candidate.environment !== 'Production',
        );

        environment =
            lastValidatedEnvironment?.environment ??
            firstReadyEnvironment?.environment ??
            next.settings.environments[0]?.environment ??
            'SIT';
    };

    const load = async (silent = false): Promise<void> => {
        if (!silent) {
            loading = true;
        }

        try {
            const response = await fetchTestCaseAutomation(testCaseId);
            hydrate(response.data);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to load automation definition.',
            );
        } finally {
            loading = false;
        }
    };

    const handleError = (error: unknown): void => {
        errors = error instanceof AutomationApiError ? error.errors : {};
        toast.error(
            error instanceof Error
                ? error.message
                : 'Unable to process the automation definition.',
        );
    };

    const generate = async (): Promise<void> => {
        action = 'generate';
        errors = {};

        try {
            const response = await generateTestCaseAutomation(testCaseId);
            warnings = response.warnings;
            hydrate(response.data);
            toast.success(
                'Automation draft generated. Review and validate it.',
            );
        } catch (error) {
            handleError(error);
        } finally {
            action = null;
        }
    };

    const save = async (): Promise<void> => {
        action = 'save';
        errors = {};

        try {
            const definition = JSON.parse(
                definitionText,
            ) as AutomationDefinition;
            const response = await updateTestCaseAutomation(
                testCaseId,
                definition,
            );
            hydrate(response.data);
            toast.success('Automation draft saved.');
        } catch (error) {
            if (error instanceof SyntaxError) {
                errors = { definition: 'The definition must be valid JSON.' };
                toast.error(errors.definition);
            } else {
                handleError(error);
            }
        } finally {
            action = null;
        }
    };

    const validateDefinition = async (): Promise<void> => {
        action = 'validate';
        errors = {};

        try {
            await validateTestCaseAutomation(testCaseId, environment);
            await load(true);
            toast.success('Browser validation queued in GitHub Actions.');
        } catch (error) {
            handleError(error);
        } finally {
            action = null;
        }
    };

    const approve = async (): Promise<void> => {
        action = 'approve';
        errors = {};

        try {
            const response = await approveTestCaseAutomation(testCaseId);
            hydrate(response.data);
            toast.success('Automation definition is Ready.');
        } catch (error) {
            handleError(error);
        } finally {
            action = null;
        }
    };

    onMount(() => {
        void load();
        const timer = window.setInterval(() => {
            if (
                payload?.automation?.status === 'Validating' ||
                payload?.automation?.status === 'Healing'
            ) {
                void load(true);
            }
        }, 5000);

        return () => window.clearInterval(timer);
    });
</script>

<section
    class="mx-auto mt-6 w-full max-w-5xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
>
    <div
        class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
    >
        <div>
            <div class="flex items-center gap-2">
                <Bot class="size-5 text-[#002b57]" />
                <h2 class="text-lg font-bold text-[#071a2f]">
                    Browser Automation
                </h2>
            </div>
            <p class="mt-1 text-sm text-slate-600">
                Generate a safe Playwright definition, validate it in GitHub
                Actions, then approve it for Test Runs.
            </p>
        </div>
        {#if payload?.automation}
            <span
                class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-[#002b57]"
            >
                {statusLabel()}
            </span>
        {/if}
    </div>

    {#if loading}
        <div class="flex min-h-40 items-center justify-center"><Spinner /></div>
    {:else if payload}
        {#if !payload.settings.enabled}
            <p
                class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                Browser automation is disabled for this environment.
            </p>
        {/if}

        <div class="mt-5 grid gap-4">
            <div class="grid gap-2">
                <label
                    for="automation-definition"
                    class="text-sm font-semibold text-[#071a2f]"
                    >Automation definition</label
                >
                <textarea
                    id="automation-definition"
                    rows="12"
                    bind:value={definitionText}
                    spellcheck="false"
                    class="min-h-56 w-full rounded-xl border border-slate-300 bg-slate-950 px-4 py-3 font-mono text-sm leading-6 text-slate-100 outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15 sm:min-h-72 lg:min-h-80"
                    placeholder="Generate an automation draft to begin."
                ></textarea>
                {#if errors.definition}
                    <p class="text-sm text-red-600">{errors.definition}</p>
                {/if}
            </div>

            {#if warnings.length > 0}
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                >
                    {#each warnings as warning (warning)}<p>{warning}</p>{/each}
                </div>
            {/if}

            {#if payload.automation?.validation_summary}
                <p
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
                >
                    {payload.automation.validation_summary}
                </p>
            {/if}

            {#if payload.automation?.healing_history.length}
                <section
                    id="automation-healing-history"
                    class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    <div>
                        <h3 class="text-sm font-bold text-[#071a2f]">
                            AI healing history
                        </h3>
                        <p class="mt-1 text-xs text-slate-600">
                            Every automatic replacement and revalidation result
                            is preserved here.
                        </p>
                    </div>
                    {#each payload.automation.healing_history as attempt (attempt.id)}
                        <article
                            class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="text-sm font-semibold text-[#071a2f]">
                                    Step {attempt.failed_step} · {attempt.status}
                                </p>
                                {#if attempt.confidence !== null}
                                    <span
                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        {attempt.confidence}% confidence
                                    </span>
                                {/if}
                            </div>
                            {#if attempt.rationale}
                                <p class="text-sm text-slate-700">
                                    {attempt.rationale}
                                </p>
                            {/if}
                            <div class="grid gap-3 lg:grid-cols-2">
                                <div class="min-w-0">
                                    <p
                                        class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Original step
                                    </p>
                                    <pre
                                        class="max-h-56 overflow-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-100">{JSON.stringify(
                                            definitionStep(
                                                attempt.original_definition,
                                                attempt.failed_step,
                                            ),
                                            null,
                                            2,
                                        )}</pre>
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Replacement step
                                    </p>
                                    <pre
                                        class="max-h-56 overflow-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-100">{JSON.stringify(
                                            definitionStep(
                                                attempt.proposed_definition,
                                                attempt.failed_step,
                                            ),
                                            null,
                                            2,
                                        )}</pre>
                                </div>
                            </div>
                            {#if attempt.failure_message}
                                <p class="text-xs text-red-700">
                                    {attempt.failure_message}
                                </p>
                            {/if}
                        </article>
                    {/each}
                </section>
            {/if}

            <div
                class="sticky bottom-0 -mx-5 border-t border-slate-200 bg-white/95 px-5 py-4 backdrop-blur lg:flex lg:items-end lg:justify-between"
            >
                <div class="grid gap-2">
                    <label
                        for="automation-validation-environment"
                        class="text-sm font-semibold text-[#071a2f]"
                        >Validation environment</label
                    >
                    <Select type="single" bind:value={environment}>
                        <SelectTrigger
                            id="automation-validation-environment"
                            class="h-10 w-full min-w-48 border-slate-300 bg-white text-left text-sm text-[#071a2f] sm:min-w-56"
                        >
                            {environment}
                        </SelectTrigger>
                        <SelectContent>
                            {#each validationEnvironments() as item (item.environment)}
                                <SelectItem value={item.environment}>
                                    {item.environment}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                    {#if validationEnvironmentMessage()}
                        <p class="text-xs text-amber-700">
                            {validationEnvironmentMessage()}
                        </p>
                    {/if}
                </div>

                <div class="mt-4 flex flex-wrap gap-2 lg:mt-0 lg:justify-end">
                    <Button
                        id="generate-automation-definition"
                        type="button"
                        variant="outline"
                        disabled={action !== null || !payload.settings.enabled}
                        onclick={generate}
                    >
                        {#if action === 'generate'}<Spinner />{:else}<Bot
                                class="size-4"
                            />{/if}
                        Generate Automation
                    </Button>
                    <Button
                        id="save-automation-definition"
                        type="button"
                        variant="outline"
                        disabled={action !== null || definitionText === ''}
                        onclick={save}
                    >
                        {#if action === 'save'}<Spinner />{:else}<Save
                                class="size-4"
                            />{/if}
                        Save Draft
                    </Button>
                    <Button
                        id="validate-automation-definition"
                        type="button"
                        variant="outline"
                        disabled={action !== null ||
                            !payload.automation ||
                            !canValidateSelectedEnvironment()}
                        onclick={validateDefinition}
                    >
                        {#if action === 'validate'}<Spinner
                            />{:else}<FlaskConical class="size-4" />{/if}
                        Validate
                    </Button>
                    <Button
                        id="approve-automation-definition"
                        type="button"
                        disabled={action !== null ||
                            !payload.automation?.last_validated_at ||
                            payload.automation.status !== 'Draft'}
                        onclick={approve}
                    >
                        {#if action === 'approve'}<Spinner
                            />{:else}<CheckCircle2 class="size-4" />{/if}
                        Approve Ready
                    </Button>
                </div>
            </div>
        </div>
    {/if}
</section>
