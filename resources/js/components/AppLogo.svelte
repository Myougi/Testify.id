<script lang="ts">
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        iconClass = 'size-8',
        wordmarkClass = 'text-xl',
        variant = 'brand',
    }: {
        class?: string;
        iconClass?: string;
        wordmarkClass?: string;
        variant?: 'brand' | 'monochrome';
    } = $props();
</script>

<span
    class={cn('inline-flex items-center gap-2.5 text-[#071a2f]', className)}
    aria-label="Testify"
>
    <AppLogoIcon
        {variant}
        class={cn('shrink-0', iconClass)}
        aria-hidden="true"
    />
    <span
        aria-hidden="true"
        class={cn(
            'font-semibold leading-none tracking-[-0.035em]',
            wordmarkClass,
        )}
    >
        Test<span class="relative inline-block"
            >i<span
                class="absolute top-[0.04em] left-1/2 size-[0.22em] -translate-x-1/2 rounded-full bg-[#0b5fff]"
            ></span></span
        >fy
    </span>
</span>
