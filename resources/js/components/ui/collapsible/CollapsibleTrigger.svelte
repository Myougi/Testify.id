<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        children,
        ...rest
    }: {
        children?: Snippet;
        [key: string]: unknown;
    } = $props();

    const id = $props.id();
</script>

<button {id} type="button" data-slot="collapsible-trigger" {...rest}>
    {@render children?.()}
</button>
