<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import type { TestPlanStatus } from '@/lib/api/testPlans';

    let {
        status,
    }: {
        status: TestPlanStatus;
    } = $props();

    const statusClass = $derived.by(() => {
        switch (status) {
            case 'Completed':
                return 'border-emerald-200 bg-emerald-50 text-emerald-700';
            case 'In Progress':
                return 'border-amber-200 bg-amber-50 text-amber-700';
            case 'Archived':
                return 'border-slate-200 bg-slate-100 text-slate-600';
            default:
                return 'border-slate-200 bg-white text-slate-600';
        }
    });
</script>

<Badge
    class={`rounded-full border px-2.5 py-1 text-xs font-semibold ${statusClass}`}
>
    {status}
</Badge>
