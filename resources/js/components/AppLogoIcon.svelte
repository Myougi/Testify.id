<script lang="ts">
    type LogoVariant = 'brand' | 'monochrome';

    let {
        class: className = '',
        variant = 'brand',
        ...rest
    }: {
        class?: string;
        variant?: LogoVariant;
        [key: string]: unknown;
    } = $props();

    const topColor = $derived(variant === 'brand' ? '#0b5fff' : 'currentColor');
    const stemColor = $derived(
        variant === 'brand' ? '#071a2f' : 'currentColor',
    );
</script>

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 48 48"
    class={className}
    role="img"
    aria-label="Testify"
    {...rest}
>
    <path
        fill={topColor}
        d="M12 3h24c5 0 9 4 9 9v11H32c-1.7 0-3 1.3-3 3v2h-8v-4c0-1.7-1.3-3-3-3H3v-9c0-5 4-9 9-9Z"
    />
    <path
        fill={stemColor}
        d="M17 24h4v4c0 1.7 1.3 3 3 3h7v-7h4v18c0 2.2-1.8 4-4 4H21c-2.2 0-4-1.8-4-4V24Z"
    />
</svg>
