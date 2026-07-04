<script lang="ts">
    import Bot from 'lucide-svelte/icons/bot';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import { onMount } from 'svelte';
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
    import { Spinner } from '@/components/ui/spinner';
    import type {
        AiDraftResponse,
        IssueAiDraft,
        IssueAiDraftSet,
    } from '@/lib/api/ai';
    import type {
        AutomationExecution,
        AutomationRun,
    } from '@/lib/api/automation';
    import {
        fetchAutomationRuns,
        generateAutomationIssueDraft,
        retryAutomationRun,
    } from '@/lib/api/automation';
    import { createIssue } from '@/lib/api/issues';

    let { testRunId }: { testRunId: number } = $props();

    let runs = $state<AutomationRun[]>([]);
    let loading = $state(true);
    let retrying = $state(false);
    let draftingExecutionId = $state<string | null>(null);
    let issueDraft = $state<IssueAiDraft | null>(null);
    let creatingIssue = $state(false);

    const load = async (silent = false): Promise<void> => {
        if (!silent) {
            loading = true;
        }

        try {
            runs = (await fetchAutomationRuns(testRunId)).data;
        } catch (error) {
            if (!silent) {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to load automation runs.',
                );
            }
        } finally {
            loading = false;
        }
    };

    const retry = async (): Promise<void> => {
        retrying = true;

        try {
            await retryAutomationRun(testRunId);
            await load(true);
            toast.success('Failed automation queued for retry.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to retry automation.',
            );
        } finally {
            retrying = false;
        }
    };

    const generateIssueDraft = async (
        execution: AutomationExecution,
    ): Promise<void> => {
        draftingExecutionId = execution.id;

        try {
            const response = await generateAutomationIssueDraft<
                AiDraftResponse<IssueAiDraftSet>
            >(execution.id);
            issueDraft = response.draft.issues[0] ?? null;
            toast.success('Issue draft generated for review.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to generate issue draft.',
            );
        } finally {
            draftingExecutionId = null;
        }
    };

    const approveIssue = async (): Promise<void> => {
        if (!issueDraft) {
            return;
        }

        creatingIssue = true;

        try {
            await createIssue({
                title: issueDraft.title,
                description: issueDraft.description,
                expected_result: issueDraft.expected_result ?? '',
                actual_result: issueDraft.actual_result ?? '',
                priority: issueDraft.priority,
                severity: issueDraft.severity,
                status: issueDraft.status,
                reporter: issueDraft.reporter,
                assignee: issueDraft.assignee ?? '',
                environment: issueDraft.environment ?? '',
                defect_type: issueDraft.defect_type ?? '',
                test_plan_id: issueDraft.test_plan_id,
                test_run_id: issueDraft.test_run_id,
            });
            toast.success('Issue created from the approved automation draft.');
            issueDraft = null;
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Unable to create issue.',
            );
        } finally {
            creatingIssue = false;
        }
    };

    const canRetry = $derived(
        runs.some((run) =>
            run.executions.some((execution) =>
                ['Failed', 'Blocked'].includes(execution.status),
            ),
        ),
    );

    onMount(() => {
        void load();
        const timer = window.setInterval(() => {
            if (
                runs.some((run) =>
                    ['Queued', 'Dispatching', 'Running'].includes(run.status),
                )
            ) {
                void load(true);
            }
        }, 5000);

        return () => window.clearInterval(timer);
    });
</script>

<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
    >
        <div>
            <div class="flex items-center gap-2">
                <Bot class="size-5 text-[#002b57]" />
                <h2 class="text-base font-semibold text-[#071a2f]">
                    Automated Execution
                </h2>
            </div>
            <p class="mt-1 text-sm text-slate-600">
                GitHub Actions browser runs, evidence, and failure triage.
            </p>
        </div>
        {#if canRetry}
            <Button
                id="retry-failed-automation"
                type="button"
                variant="outline"
                disabled={retrying}
                onclick={retry}
            >
                {#if retrying}<Spinner />{:else}<RefreshCw
                        class="size-4"
                    />{/if}
                Retry Failed Automation
            </Button>
        {/if}
    </div>

    {#if loading}
        <div class="flex min-h-28 items-center justify-center"><Spinner /></div>
    {:else if runs.length === 0}
        <p
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500"
        >
            No automated execution has been dispatched for this Test Run.
        </p>
    {:else}
        <div class="mt-4 grid gap-4">
            {#each runs as run (run.id)}
                <article class="rounded-xl border border-slate-200">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3"
                    >
                        <div>
                            <p class="font-semibold text-[#071a2f]">
                                Attempt {run.attempt} · {run.environment}
                            </p>
                            <p class="text-xs text-slate-500">{run.status}</p>
                        </div>
                        {#if run.provider_run_url}
                            <a
                                href={run.provider_run_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-sm font-semibold text-[#002b57]"
                            >
                                GitHub run <ExternalLink class="size-3.5" />
                            </a>
                        {/if}
                    </div>
                    <div class="divide-y divide-slate-200">
                        {#each run.executions as execution (execution.id)}
                            <div
                                class="grid gap-3 px-4 py-4 lg:grid-cols-[1fr_auto] lg:items-start"
                            >
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p class="font-semibold text-[#071a2f]">
                                            {execution.case_key}
                                        </p>
                                        <span
                                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"
                                            >{execution.status}</span
                                        >
                                    </div>
                                    {#if execution.failure_message}
                                        <p class="mt-2 text-sm text-red-700">
                                            {execution.failure_message}
                                        </p>
                                    {/if}
                                    {#if execution.artifacts.length > 0}
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            {#each execution.artifacts as artifact (artifact.id)}
                                                <a
                                                    href={`/automation-artifacts/${artifact.id}`}
                                                    class="text-xs font-semibold text-[#002b57] hover:underline"
                                                >
                                                    {artifact.type}: {artifact.name}
                                                </a>
                                            {/each}
                                        </div>
                                    {/if}
                                </div>
                                {#if ['Failed', 'Blocked'].includes(execution.status)}
                                    <Button
                                        id={`generate-issue-draft-${execution.id}`}
                                        type="button"
                                        variant="outline"
                                        disabled={draftingExecutionId !== null}
                                        onclick={() =>
                                            generateIssueDraft(execution)}
                                    >
                                        {#if draftingExecutionId === execution.id}<Spinner
                                            />{:else}<Bot class="size-4" />{/if}
                                        Generate Issue Draft
                                    </Button>
                                {/if}
                            </div>
                        {/each}
                    </div>
                </article>
            {/each}
        </div>
    {/if}
</section>

<Dialog
    open={Boolean(issueDraft)}
    onOpenChange={(open) => !open && (issueDraft = null)}
>
    <DialogContent class="max-w-2xl border-slate-200 bg-white text-[#071a2f]">
        <DialogTitle>Review Automation Issue Draft</DialogTitle>
        <DialogDescription
            >No Issue is created until you approve this draft.</DialogDescription
        >
        {#if issueDraft}
            <div class="mt-4 grid gap-4 text-sm">
                <div>
                    <p class="font-semibold">Title</p>
                    <p class="mt-1 text-slate-700">{issueDraft.title}</p>
                </div>
                <div>
                    <p class="font-semibold">Description</p>
                    <p class="mt-1 whitespace-pre-wrap text-slate-700">
                        {issueDraft.description}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="font-semibold">Priority</p>
                        <p>{issueDraft.priority}</p>
                    </div>
                    <div>
                        <p class="font-semibold">Severity</p>
                        <p>{issueDraft.severity}</p>
                    </div>
                </div>
            </div>
        {/if}
        <DialogFooter class="mt-5">
            <DialogClose asChild>
                {#snippet children(closeProps)}<Button
                        type="button"
                        variant="outline"
                        onclick={closeProps.onClick}>Cancel</Button
                    >{/snippet}
            </DialogClose>
            <Button
                id="approve-automation-issue-draft"
                type="button"
                disabled={creatingIssue}
                onclick={approveIssue}
            >
                {#if creatingIssue}<Spinner />{/if} Approve and Create Issue
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
