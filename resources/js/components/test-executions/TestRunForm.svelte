<script lang="ts">
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';
    import FileText from 'lucide-svelte/icons/file-text';
    import Flag from 'lucide-svelte/icons/flag';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import type {
        TestRunEnvironment,
        TestRunFormValues,
        TestRunPlanOption,
        TestRunPriority,
    } from '@/lib/api/testRuns';

    let {
        values,
        errors = {},
        processing = false,
        testPlans = [],
        environments = [],
        priorities = [],
        submitLabel,
        onsubmit,
        oncancel,
    }: {
        values: TestRunFormValues;
        errors?: Record<string, string>;
        processing?: boolean;
        testPlans?: TestRunPlanOption[];
        environments?: TestRunEnvironment[];
        priorities?: TestRunPriority[];
        submitLabel: string;
        onsubmit: () => void;
        oncancel: () => void;
    } = $props();

    const selectedPlan = $derived(
        testPlans.find((testPlan) => testPlan.id === values.test_plan_id) ??
            null,
    );

    const selectedCases = $derived(selectedPlan?.test_cases ?? []);

    const toggleCase = (testCaseId: number): void => {
        if (values.test_case_ids.includes(testCaseId)) {
            values.test_case_ids = values.test_case_ids.filter(
                (id) => id !== testCaseId,
            );

            return;
        }

        values.test_case_ids = [...values.test_case_ids, testCaseId];
    };

    const toggleAllCases = (checked: boolean): void => {
        values.test_case_ids = checked
            ? selectedCases.map((testCase) => testCase.id)
            : [];
    };

    const allCasesSelected = $derived(
        selectedCases.length > 0 &&
            selectedCases.every((testCase) =>
                values.test_case_ids.includes(testCase.id),
            ),
    );

    const hasAvailablePlans = $derived(testPlans.length > 0);
    const canSubmit = $derived(
        hasAvailablePlans &&
            selectedPlan !== null &&
            values.test_case_ids.length > 0 &&
            values.name.trim() !== '' &&
            values.tester_name.trim() !== '',
    );
</script>

<form
    class="mt-6 grid gap-5"
    onsubmit={(event) => {
        event.preventDefault();
        onsubmit();
    }}
>
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div
            class="flex items-center gap-3 border-b border-slate-200 px-5 py-4"
        >
            <span
                class="flex size-9 items-center justify-center rounded-xl bg-[#eff5fb] text-[#002b57]"
            >
                <FileText class="size-4" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#071a2f]">
                    Run Details
                </h3>
                <p class="text-xs text-slate-500">
                    Define the execution scope and owner.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5">
            {#if !hasAvailablePlans}
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    Create a Test Plan first before creating a Test Run.
                </div>
            {/if}

            <div class="grid gap-2">
                <Label for="test_run_name">Test run name</Label>
                <Input
                    id="test_run_name"
                    name="name"
                    required
                    bind:value={values.name}
                    class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                    placeholder="e.g., Nightly Core Regression"
                />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="test_run_plan">Test plan</Label>
                    <select
                        id="test_run_plan"
                        required
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        value={values.test_plan_id ?? ''}
                        onchange={(event) => {
                            values.test_plan_id =
                                Number(
                                    (event.currentTarget as HTMLSelectElement)
                                        .value,
                                ) || null;
                            values.test_case_ids = [];
                        }}
                    >
                        <option value="">Select test plan</option>
                        {#each testPlans as testPlan (testPlan.id)}
                            <option value={testPlan.id}>
                                {testPlan.name}
                            </option>
                        {/each}
                    </select>
                    <InputError message={errors.test_plan_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_run_tester">Tester name</Label>
                    <Input
                        id="test_run_tester"
                        required
                        bind:value={values.tester_name}
                        class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                        placeholder="Enter tester name"
                    />
                    <InputError message={errors.tester_name} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_run_environment">Test environment</Label>
                    <select
                        id="test_run_environment"
                        required
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={values.environment}
                    >
                        {#each environments as environment (environment)}
                            <option value={environment}>{environment}</option>
                        {/each}
                    </select>
                    <InputError message={errors.environment} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_run_priority">Priority</Label>
                    <select
                        id="test_run_priority"
                        required
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={values.priority}
                    >
                        {#each priorities as priority (priority)}
                            <option value={priority}>{priority}</option>
                        {/each}
                    </select>
                    <InputError message={errors.priority} />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="test_run_description">Description</Label>
                <textarea
                    id="test_run_description"
                    rows="4"
                    bind:value={values.description}
                    class="flex min-h-28 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="Add execution context, release notes, or setup details..."
                ></textarea>
                <InputError message={errors.description} />
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div
            class="flex items-center gap-3 border-b border-slate-200 px-5 py-4"
        >
            <span
                class="flex size-9 items-center justify-center rounded-xl bg-[#eff5fb] text-[#002b57]"
            >
                <ClipboardList class="size-4" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#071a2f]">Test Cases</h3>
                <p class="text-xs text-slate-500">
                    Select cases already assigned to the chosen test plan.
                </p>
            </div>
        </div>

        <div class="px-5 py-5">
            {#if !selectedPlan}
                <div
                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                >
                    Select a test plan before choosing test cases.
                </div>
            {:else if selectedCases.length === 0}
                <div
                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                >
                    This test plan does not have assigned test cases yet.
                </div>
            {:else}
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <div
                        class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-4 py-3"
                    >
                        <label
                            class="flex items-center gap-3 text-sm font-medium text-[#071a2f]"
                        >
                            <input
                                id="test-run-select-all-cases"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#002b57]"
                                checked={allCasesSelected}
                                onchange={(event) =>
                                    toggleAllCases(
                                        (
                                            event.currentTarget as HTMLInputElement
                                        ).checked,
                                    )}
                            />
                            Select all visible cases
                        </label>
                        <span class="text-sm text-slate-500">
                            {values.test_case_ids.length} selected
                        </span>
                    </div>

                    <div class="max-h-72 overflow-y-auto">
                        {#each selectedCases as testCase (testCase.id)}
                            <label
                                class="grid cursor-pointer gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50 md:grid-cols-[auto_1fr_auto]"
                            >
                                <input
                                    id={`test-run-case-${testCase.id}-select`}
                                    type="checkbox"
                                    class="mt-1 rounded border-slate-300 text-[#002b57]"
                                    checked={values.test_case_ids.includes(
                                        testCase.id,
                                    )}
                                    onchange={() => toggleCase(testCase.id)}
                                />
                                <span>
                                    <span
                                        class="block font-semibold text-[#002b57]"
                                    >
                                        {testCase.title}
                                    </span>
                                    <span class="text-xs text-slate-500">
                                        {testCase.case_key} - {testCase.module
                                            .name ?? 'Uncategorized'}
                                    </span>
                                </span>
                                <span
                                    class="self-start rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                >
                                    {testCase.priority}
                                </span>
                            </label>
                        {/each}
                    </div>
                </div>
            {/if}
            <InputError message={errors.test_case_ids} />
        </div>
    </section>

    <div
        class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5"
    >
        <Button type="button" variant="outline" onclick={oncancel}>
            Cancel
        </Button>
        <Button
            type="submit"
            class="bg-[#002b57] text-white hover:bg-[#003c78]"
            disabled={processing || !canSubmit}
        >
            {#if processing}<Spinner />{/if}
            <Flag class="size-4" />
            {submitLabel}
        </Button>
    </div>
</form>
