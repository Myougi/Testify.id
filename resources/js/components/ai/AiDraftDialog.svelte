<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Spinner } from '@/components/ui/spinner';

    let {
        open = $bindable(false),
        title,
        description,
        prompt = $bindable(''),
        processing = false,
        error = null,
        warnings = [],
        contextItems = [],
        hasDraft = false,
        generateLabel = 'Generate Draft',
        emptyLabel = 'Generate a draft to preview it here.',
        ongenerate,
        controls,
        preview,
    }: {
        open: boolean;
        title: string;
        description: string;
        prompt: string;
        processing?: boolean;
        error?: string | null;
        warnings?: string[];
        contextItems?: string[];
        hasDraft?: boolean;
        generateLabel?: string;
        emptyLabel?: string;
        ongenerate: () => void;
        controls?: Snippet;
        preview?: Snippet;
    } = $props();

    const canGenerate = $derived(prompt.trim() !== '' && !processing);
</script>

<Dialog bind:open>
    <DialogContent
        class="max-h-[calc(100vh-2rem)] overflow-y-auto border-slate-200 bg-white text-[#071a2f] sm:max-w-4xl"
    >
        <DialogTitle>{title}</DialogTitle>
        <DialogDescription>{description}</DialogDescription>

        <div class="mt-5 grid gap-4">
            <div class="grid gap-2">
                <label
                    for="ai-draft-prompt"
                    class="text-sm font-semibold text-[#071a2f]"
                >
                    Prompt
                </label>
                <textarea
                    id="ai-draft-prompt"
                    rows="5"
                    bind:value={prompt}
                    class="flex min-h-32 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="Describe what you want the QA agent to generate..."
                ></textarea>
            </div>

            {#if controls}
                <div class="grid gap-3">
                    {@render controls()}
                </div>
            {/if}

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    {#each contextItems as item (item)}
                        <span
                            class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                        >
                            {item}
                        </span>
                    {/each}
                </div>

                <Button
                    type="button"
                    class="bg-[#002b57] text-white hover:bg-[#003c78]"
                    disabled={!canGenerate}
                    onclick={ongenerate}
                >
                    {#if processing}<Spinner />{/if}
                    {generateLabel}
                </Button>
            </div>

            {#if error}
                <div
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {error}
                </div>
            {/if}

            {#if warnings.length > 0}
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
                >
                    <p class="text-sm font-semibold text-amber-900">
                        Review notes
                    </p>
                    <ul class="mt-2 grid gap-2 text-sm text-amber-800">
                        {#each warnings as warning (warning)}
                            <li>{warning}</li>
                        {/each}
                    </ul>
                </div>
            {/if}

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                {#if hasDraft && preview}
                    {@render preview()}
                {:else}
                    <p class="text-sm text-slate-500">{emptyLabel}</p>
                {/if}
            </div>
        </div>
    </DialogContent>
</Dialog>
