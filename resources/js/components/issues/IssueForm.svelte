<script lang="ts">
    import Link2 from 'lucide-svelte/icons/link-2';
    import ListTodo from 'lucide-svelte/icons/list-todo';
    import UserRound from 'lucide-svelte/icons/user-round';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import type {
        IssueDefectType,
        IssueEnvironment,
        IssueFormValues,
        IssuePlanOption,
        IssuePriority,
        IssueRunOption,
        IssueSeverity,
        IssueStatus,
    } from '@/lib/api/issues';

    let {
        values,
        errors = {},
        processing = false,
        testPlans = [],
        testRuns = [],
        priorities = [],
        severities = [],
        statuses = [],
        environments = [],
        defectTypes = [],
        submitLabel,
        onsubmit,
        oncancel,
    }: {
        values: IssueFormValues;
        errors?: Record<string, string>;
        processing?: boolean;
        testPlans?: IssuePlanOption[];
        testRuns?: IssueRunOption[];
        priorities?: IssuePriority[];
        severities?: IssueSeverity[];
        statuses?: IssueStatus[];
        environments?: IssueEnvironment[];
        defectTypes?: IssueDefectType[];
        submitLabel: string;
        onsubmit: () => void;
        oncancel: () => void;
    } = $props();

    const filteredRuns = $derived(
        values.test_plan_id === null
            ? testRuns
            : testRuns.filter(
                  (testRun) => testRun.test_plan.id === values.test_plan_id,
              ),
    );

    const selectedRun = $derived(
        testRuns.find((testRun) => testRun.id === values.test_run_id) ?? null,
    );

    const canSubmit = $derived(
        values.title.trim() !== '' &&
            values.description.trim() !== '' &&
            values.reporter.trim() !== '',
    );

    const onPlanChange = (value: string): void => {
        values.test_plan_id = Number(value) || null;

        if (
            selectedRun &&
            values.test_plan_id !== null &&
            selectedRun.test_plan.id !== values.test_plan_id
        ) {
            values.test_run_id = null;
        }
    };

    const onRunChange = (value: string): void => {
        const testRunId = Number(value) || null;
        values.test_run_id = testRunId;

        const nextRun =
            testRuns.find((testRun) => testRun.id === testRunId) ?? null;

        if (nextRun) {
            values.test_plan_id = nextRun.test_plan.id;
        }
    };
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
                <ListTodo class="size-4" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#071a2f]">
                    Issue Details
                </h3>
                <p class="text-xs text-slate-500">
                    Capture the defect, severity, and current state.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5">
            <div class="grid gap-2">
                <Label for="issue_title">Issue Title</Label>
                <Input
                    id="issue_title"
                    bind:value={values.title}
                    class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                    placeholder="e.g., Checkout hangs after payment confirmation"
                />
                <InputError message={errors.title} />
            </div>

            <div class="grid gap-2">
                <Label for="issue_description">Step to Reproduce</Label>
                <textarea
                    id="issue_description"
                    rows="5"
                    bind:value={values.description}
                    class="flex min-h-32 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    placeholder="List the steps needed to reproduce this issue..."
                ></textarea>
                <InputError message={errors.description} />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="issue_expected_result">Expected Result</Label>
                    <textarea
                        id="issue_expected_result"
                        rows="4"
                        bind:value={values.expected_result}
                        class="flex min-h-28 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        placeholder="Describe the intended system behavior..."
                    ></textarea>
                    <InputError message={errors.expected_result} />
                </div>

                <div class="grid gap-2">
                    <Label for="issue_actual_result">Actual Result</Label>
                    <textarea
                        id="issue_actual_result"
                        rows="4"
                        bind:value={values.actual_result}
                        class="flex min-h-28 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        placeholder="Describe what happened instead..."
                    ></textarea>
                    <InputError message={errors.actual_result} />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="issue_priority">Priority</Label>
                    <select
                        id="issue_priority"
                        bind:value={values.priority}
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    >
                        {#each priorities as priority (priority)}
                            <option value={priority}>{priority}</option>
                        {/each}
                    </select>
                    <InputError message={errors.priority} />
                </div>

                <div class="grid gap-2">
                    <Label for="issue_severity">Severity</Label>
                    <select
                        id="issue_severity"
                        bind:value={values.severity}
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    >
                        {#each severities as severity (severity)}
                            <option value={severity}>{severity}</option>
                        {/each}
                    </select>
                    <InputError message={errors.severity} />
                </div>

                <div class="grid gap-2">
                    <Label for="issue_status">Status</Label>
                    <select
                        id="issue_status"
                        bind:value={values.status}
                        class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    >
                        {#each statuses as status (status)}
                            <option value={status}>{status}</option>
                        {/each}
                    </select>
                    <InputError message={errors.status} />
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
                <UserRound class="size-4" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#071a2f]">
                    Ownership And Context
                </h3>
                <p class="text-xs text-slate-500">
                    Track who raised the issue and where it was found.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="issue_reporter">Reporter</Label>
                <Input
                    id="issue_reporter"
                    bind:value={values.reporter}
                    class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                    placeholder="Who reported it?"
                />
                <InputError message={errors.reporter} />
            </div>

            <div class="grid gap-2">
                <Label for="issue_assignee">Assignee</Label>
                <Input
                    id="issue_assignee"
                    bind:value={values.assignee}
                    class="h-11 border-slate-300 bg-white text-[#071a2f] placeholder:text-slate-400"
                    placeholder="Who is fixing it?"
                />
                <InputError message={errors.assignee} />
            </div>

            <div class="grid gap-2">
                <Label for="issue_environment">Environment</Label>
                <select
                    id="issue_environment"
                    bind:value={values.environment}
                    class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                >
                    <option value="">Select environment</option>
                    {#each environments as environment (environment)}
                        <option value={environment}>{environment}</option>
                    {/each}
                </select>
                <InputError message={errors.environment} />
            </div>

            <div class="grid gap-2">
                <Label for="issue_defect_type">Defect Type</Label>
                <select
                    id="issue_defect_type"
                    bind:value={values.defect_type}
                    class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                >
                    <option value="">Select defect type</option>
                    {#each defectTypes as defectType (defectType)}
                        <option value={defectType}>{defectType}</option>
                    {/each}
                </select>
                <InputError message={errors.defect_type} />
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
                <Link2 class="size-4" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#071a2f]">
                    Linked Artifacts
                </h3>
                <p class="text-xs text-slate-500">
                    Optionally connect this issue to a plan or execution run.
                </p>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="issue_test_plan">Linked Test Plan</Label>
                <select
                    id="issue_test_plan"
                    class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    value={values.test_plan_id ?? ''}
                    onchange={(event) =>
                        onPlanChange(
                            (event.currentTarget as HTMLSelectElement).value,
                        )}
                >
                    <option value="">No linked test plan</option>
                    {#each testPlans as testPlan (testPlan.id)}
                        <option value={testPlan.id}>{testPlan.name}</option>
                    {/each}
                </select>
                <InputError message={errors.test_plan_id} />
            </div>

            <div class="grid gap-2">
                <Label for="issue_test_run">Linked Test Run</Label>
                <select
                    id="issue_test_run"
                    class="flex h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                    value={values.test_run_id ?? ''}
                    onchange={(event) =>
                        onRunChange(
                            (event.currentTarget as HTMLSelectElement).value,
                        )}
                >
                    <option value="">No linked test run</option>
                    {#each filteredRuns as testRun (testRun.id)}
                        <option value={testRun.id}>
                            {testRun.name}
                        </option>
                    {/each}
                </select>
                <InputError message={errors.test_run_id} />
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
            disabled={processing || !canSubmit}
        >
            {#if processing}<Spinner />{/if}
            {submitLabel}
        </Button>
    </div>
</form>
