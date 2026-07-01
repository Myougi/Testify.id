<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT } from './context';
    import type { DialogContext } from './context';

    let { class: className = '', children }: { class?: string; children?: Snippet } = $props();

    const { open, setOpen } = getContext<DialogContext>(DIALOG_CONTEXT);
    const id = $props.id();

    const close = () => setOpen(false);
</script>

{#if open()}
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6"
    >
        <button
            id={`${id}-overlay`}
            type="button"
            class="fixed inset-0 bg-black/50"
            aria-label="Close"
            onclick={close}
        ></button>
        <div
            id={id}
            class={cn(
                'relative z-10 my-auto w-full max-w-lg rounded-lg border bg-background p-6 pr-12 shadow-lg',
                className,
            )}
            role="dialog"
            aria-modal="true"
        >
            <button
                id={`${id}-close`}
                type="button"
                class="ring-offset-background focus-visible:ring-ring absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:pointer-events-none"
                aria-label="Close dialog"
                onclick={close}
            >
                <X class="size-4" />
                <span class="sr-only">Close dialog</span>
            </button>
            {@render children?.()}
        </div>
    </div>
{/if}
