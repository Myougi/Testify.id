<script lang="ts">
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';
    import FileText from 'lucide-svelte/icons/file-text';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import type {
        TestPlanFormValues,
        TestPlanOption,
        TestPlanPriority,
        TestPlanStatus,
    } from '@/lib/api/testPlans';

    let {
        values,
        errors = {},
        processing = false,
        projects = [],
        statuses = [],
        priorities = [],
        projectDisabled = false,
        submitLabel,
        onsubmit,
        oncancel,
    }: {
        values: TestPlanFormValues;
        errors?: Record<string, string>;
        processing?: boolean;
        projects?: TestPlanOption[];
        statuses?: TestPlanStatus[];
        priorities?: TestPlanPriority[];
        projectDisabled?: boolean;
        submitLabel: string;
        onsubmit: () => void;
        oncancel: () => void;
    } = $props();
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
                    General Information
                </h3>
                <p class="text-xs text-slate-500">
                    Define the core details for this test plan.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5">
            <div class="grid gap-2">
                <Label for="test_plan_name">Test plan title</Label>
                <Input
                    id="test_plan_name"
                    name="name"
                    required
                    bind:value={values.name}
                    class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                    placeholder="e.g., Q4 Regression - Payments Module"
                />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="test_plan_description">Description</Label>
                <textarea
                    id="test_plan_description"
                    rows="4"
                    bind:value={values.description}
                    class="flex min-h-28 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="Provide a brief overview of the test plan objectives..."
                ></textarea>
                <InputError message={errors.description} />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="test_plan_project_id">Project selection</Label>
                    <select
                        id="test_plan_project_id"
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                        value={values.project_id ?? ''}
                        disabled={projectDisabled}
                        onchange={(event) => {
                            values.project_id =
                                Number(
                                    (event.currentTarget as HTMLSelectElement)
                                        .value,
                                ) || null;
                        }}
                    >
                        <option value="">Select project</option>
                        {#each projects as project (project.id)}
                            <option value={project.id}>{project.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.project_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_plan_status">Status</Label>
                    <select
                        id="test_plan_status"
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={values.status}
                    >
                        {#each statuses as status (status)}
                            <option value={status}>{status}</option>
                        {/each}
                    </select>
                    <InputError message={errors.status} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_plan_priority">Priority</Label>
                    <select
                        id="test_plan_priority"
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        bind:value={values.priority}
                    >
                        {#each priorities as priority (priority)}
                            <option value={priority}>{priority}</option>
                        {/each}
                    </select>
                    <InputError message={errors.priority} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_plan_release_version"
                        >Release / version</Label
                    >
                    <Input
                        id="test_plan_release_version"
                        bind:value={values.release_version}
                        class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                        placeholder="v2.4.0-beta"
                    />
                    <InputError message={errors.release_version} />
                </div>

                <div class="grid gap-2">
                    <Label for="test_plan_tester">Assigned tester</Label>
                    <Input
                        id="test_plan_tester"
                        bind:value={values.tester}
                        class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                        placeholder="Enter tester name or email"
                    />
                    <InputError message={errors.tester} />
                </div>
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
                <CalendarDays class="size-4" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#071a2f]">
                    Execution Timeline
                </h3>
                <p class="text-xs text-slate-500">
                    Set the target window for this cycle.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="test_plan_start_date">Start date</Label>
                <Input
                    id="test_plan_start_date"
                    type="date"
                    bind:value={values.start_date}
                    class="h-11 border-slate-300 bg-white text-[#071a2f]"
                />
                <InputError message={errors.start_date} />
            </div>

            <div class="grid gap-2">
                <Label for="test_plan_due_date">Due date</Label>
                <Input
                    id="test_plan_due_date"
                    type="date"
                    bind:value={values.due_date}
                    class="h-11 border-slate-300 bg-white text-[#071a2f]"
                />
                <InputError message={errors.due_date} />
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
                <h3 class="text-sm font-semibold text-[#071a2f]">
                    Test Methodology
                </h3>
                <p class="text-xs text-slate-500">
                    Document the plan scope and approach.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5">
            <div class="grid gap-2">
                <Label for="test_plan_scope">Scope</Label>
                <textarea
                    id="test_plan_scope"
                    rows="4"
                    bind:value={values.scope}
                    class="flex min-h-24 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="Define what is included and excluded from testing..."
                ></textarea>
                <InputError message={errors.scope} />
            </div>

            <div class="grid gap-2">
                <Label for="test_plan_strategy">Strategy</Label>
                <textarea
                    id="test_plan_strategy"
                    rows="4"
                    bind:value={values.strategy}
                    class="flex min-h-24 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="Describe the testing approach, tools, and techniques..."
                ></textarea>
                <InputError message={errors.strategy} />
            </div>

            <div class="grid gap-2">
                <Label for="test_plan_criteria">Criteria</Label>
                <textarea
                    id="test_plan_criteria"
                    rows="4"
                    bind:value={values.criteria}
                    class="flex min-h-24 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="Define entry and exit criteria for this test plan..."
                ></textarea>
                <InputError message={errors.criteria} />
            </div>
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
            disabled={processing}
        >
            {#if processing}<Spinner />{/if}
            {submitLabel}
        </Button>
    </div>
</form>
