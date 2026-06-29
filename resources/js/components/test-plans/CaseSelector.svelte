<script lang="ts">
    import Search from 'lucide-svelte/icons/search';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import type {
        AvailableCaseFilters,
        AvailableTestCasesResponse,
    } from '@/lib/api/testPlans';

    type ModuleWithDepth =
        AvailableTestCasesResponse['options']['modules'][number] & {
            depth: number;
        };

    let {
        payload,
        filters,
        loading = false,
        adding = false,
        selectedCaseIds = [],
        onsearch,
        onfilterchange,
        onpagechange,
        ontogglecase,
        ontogglevisible,
        onclearselection,
        onaddselected,
    }: {
        payload: AvailableTestCasesResponse;
        filters: AvailableCaseFilters;
        loading?: boolean;
        adding?: boolean;
        selectedCaseIds?: number[];
        onsearch: () => void;
        onfilterchange: (nextFilters: Partial<AvailableCaseFilters>) => void;
        onpagechange: (page: number) => void;
        ontogglecase: (testCaseId: number) => void;
        ontogglevisible: (testCaseIds: number[], checked: boolean) => void;
        onclearselection: () => void;
        onaddselected: () => void;
    } = $props();

    const childModules = (
        modules: AvailableTestCasesResponse['options']['modules'],
        parentId: number | null,
    ): AvailableTestCasesResponse['options']['modules'] =>
        modules
            .filter((module) => module.parent_id === parentId)
            .sort(
                (first, second) =>
                    first.sort_order - second.sort_order ||
                    first.name.localeCompare(second.name),
            );

    const flattenModules = (
        modules: AvailableTestCasesResponse['options']['modules'],
        parentId: number | null = null,
        depth = 0,
    ): ModuleWithDepth[] =>
        childModules(modules, parentId).flatMap((module) => [
            { ...module, depth },
            ...flattenModules(modules, module.id, depth + 1),
        ]);

    const visibleCaseIds = $derived(
        payload.data.map((testCase) => testCase.id),
    );
    const flatModules = $derived(flattenModules(payload.options.modules));
    const allVisibleSelected = $derived(
        visibleCaseIds.length > 0 &&
            visibleCaseIds.every((testCaseId) =>
                selectedCaseIds.includes(testCaseId),
            ),
    );
    const pageNumbers = $derived.by(() =>
        Array.from({ length: payload.meta.last_page }, (_, index) => index + 1),
    );
</script>

<section
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
>
    <div
        class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-6"
    >
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[16rem]">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                />
                <Input
                    id="available-cases-search"
                    aria-label="Search available test cases"
                    bind:value={filters.search}
                    class="border-slate-300 bg-white pl-9 text-[#071a2f] placeholder:text-slate-400"
                    placeholder="Search by case key, title, or module"
                    onkeydown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            onsearch();
                        }
                    }}
                />
            </div>

            <select
                id="available-cases-module-filter"
                aria-label="Filter available cases by module"
                bind:value={filters.module_id}
                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                onchange={(event) =>
                    onfilterchange({
                        module_id:
                            Number(
                                (event.currentTarget as HTMLSelectElement)
                                    .value,
                            ) || null,
                    })}
            >
                <option value="">All modules</option>
                {#each flatModules as module (module.id)}
                    <option value={module.id}>
                        {'- '.repeat(module.depth)}{module.name}
                    </option>
                {/each}
            </select>

            <select
                id="available-cases-priority-filter"
                aria-label="Filter available cases by priority"
                bind:value={filters.priority}
                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                onchange={(event) =>
                    onfilterchange({
                        priority: (event.currentTarget as HTMLSelectElement)
                            .value,
                    })}
            >
                <option value="all">All priorities</option>
                {#each payload.options.priorities as priority (priority)}
                    <option value={priority}>{priority}</option>
                {/each}
            </select>

            <select
                id="available-cases-automation-filter"
                aria-label="Filter available cases by automation status"
                bind:value={filters.automation_status}
                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                onchange={(event) =>
                    onfilterchange({
                        automation_status: (
                            event.currentTarget as HTMLSelectElement
                        ).value,
                    })}
            >
                <option value="all">All automation</option>
                {#each payload.options.automationStatuses as status (status)}
                    <option value={status}>{status}</option>
                {/each}
            </select>

            <select
                id="available-cases-behavior-filter"
                aria-label="Filter available cases by behavior"
                bind:value={filters.test_behavior}
                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                onchange={(event) =>
                    onfilterchange({
                        test_behavior: (
                            event.currentTarget as HTMLSelectElement
                        ).value,
                    })}
            >
                <option value="all">All behaviors</option>
                {#each payload.options.behaviors as behavior (behavior)}
                    <option value={behavior}>{behavior}</option>
                {/each}
            </select>

            <select
                id="available-cases-type-filter"
                aria-label="Filter available cases by type"
                bind:value={filters.test_type}
                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                onchange={(event) =>
                    onfilterchange({
                        test_type: (event.currentTarget as HTMLSelectElement)
                            .value,
                    })}
            >
                <option value="all">All types</option>
                {#each payload.options.types as type (type)}
                    <option value={type}>{type}</option>
                {/each}
            </select>

            <Button
                type="button"
                variant="outline"
                class="border-slate-200 bg-white"
                onclick={onsearch}
            >
                Search
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
            <span>{selectedCaseIds.length} selected</span>
            <Button
                type="button"
                variant="outline"
                class="border-slate-200 bg-white"
                disabled={selectedCaseIds.length === 0}
                onclick={onclearselection}
            >
                Clear
            </Button>
            <Button
                type="button"
                class="bg-[#002b57] text-white hover:bg-[#003c78]"
                disabled={selectedCaseIds.length === 0 || adding}
                onclick={onaddselected}
            >
                {#if adding}<Spinner />{/if}
                Add selected
            </Button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table
            id="available-test-cases-table"
            aria-label="Available test cases"
            class="min-w-full divide-y divide-slate-200"
        >
            <thead class="bg-slate-50/80">
                <tr
                    class="text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
                >
                    <th class="w-12 px-4 py-4">
                        <input
                            id="available-cases-select-all"
                            type="checkbox"
                            class="rounded border-slate-300 text-[#002b57]"
                            aria-label="Select visible test cases"
                            checked={allVisibleSelected}
                            onchange={(event) =>
                                ontogglevisible(
                                    visibleCaseIds,
                                    (event.currentTarget as HTMLInputElement)
                                        .checked,
                                )}
                        />
                    </th>
                    <th class="px-4 py-4">Case</th>
                    <th class="px-4 py-4">Module</th>
                    <th class="px-4 py-4 text-center">Priority</th>
                    <th class="px-4 py-4">Automation</th>
                    <th class="px-4 py-4">Behavior / Type</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                {#if loading}
                    <tr>
                        <td
                            colspan="6"
                            class="px-6 py-16 text-center text-sm text-slate-500"
                        >
                            <div class="flex items-center justify-center gap-3">
                                <Spinner />
                                Loading available test cases...
                            </div>
                        </td>
                    </tr>
                {:else if payload.data.length === 0}
                    <tr>
                        <td
                            colspan="6"
                            class="px-6 py-16 text-center text-sm text-slate-500"
                        >
                            No available test cases matched the current filters.
                        </td>
                    </tr>
                {:else}
                    {#each payload.data as testCase (testCase.id)}
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-5">
                                <input
                                    id={`available-case-${testCase.id}-select`}
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#002b57]"
                                    aria-label={`Select ${testCase.title}`}
                                    checked={selectedCaseIds.includes(
                                        testCase.id,
                                    )}
                                    onchange={() => ontogglecase(testCase.id)}
                                />
                            </td>
                            <td class="px-4 py-5">
                                <div class="space-y-1">
                                    <p class="font-semibold text-[#002b57]">
                                        {testCase.title}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {testCase.case_key}
                                    </p>
                                </div>
                            </td>
                            <td class="px-4 py-5 text-sm text-slate-600">
                                {testCase.module.name ?? 'Uncategorized'}
                            </td>
                            <td class="px-4 py-5 text-center">
                                <span
                                    class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                >
                                    {testCase.priority}
                                </span>
                            </td>
                            <td class="px-4 py-5 text-sm text-slate-600">
                                {testCase.automation_status}
                            </td>
                            <td class="px-4 py-5 text-sm text-slate-600">
                                <div class="flex flex-col gap-1">
                                    <span class="font-medium text-[#071a2f]">
                                        {testCase.test_behavior}
                                    </span>
                                    <span>{testCase.test_type}</span>
                                </div>
                            </td>
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>

    <div
        class="flex flex-col gap-4 border-t border-slate-200 px-4 py-4 md:flex-row md:items-center md:justify-between lg:px-6"
    >
        <div class="flex items-center gap-3 text-sm text-slate-600">
            <span>Rows per page:</span>
            <select
                id="available-cases-rows-per-page"
                aria-label="Available cases rows per page"
                bind:value={filters.per_page}
                class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                onchange={(event) =>
                    onfilterchange({
                        per_page: Number(
                            (event.currentTarget as HTMLSelectElement).value,
                        ),
                    })}
            >
                <option value={10}>10</option>
                <option value={20}>20</option>
                <option value={50}>50</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                class="border-slate-200 bg-white"
                disabled={filters.page <= 1 || loading}
                onclick={() => onpagechange(filters.page - 1)}
            >
                Prev
            </Button>
            {#each pageNumbers as pageNumber (pageNumber)}
                <Button
                    variant={pageNumber === filters.page
                        ? 'default'
                        : 'outline'}
                    class={pageNumber === filters.page
                        ? 'bg-[#002b57] text-white hover:bg-[#003c78]'
                        : 'border-slate-200 bg-white'}
                    disabled={loading}
                    onclick={() => onpagechange(pageNumber)}
                >
                    {pageNumber}
                </Button>
            {/each}
            <Button
                variant="outline"
                class="border-slate-200 bg-white"
                disabled={filters.page >= payload.meta.last_page || loading}
                onclick={() => onpagechange(filters.page + 1)}
            >
                Next
            </Button>
        </div>
    </div>
</section>
