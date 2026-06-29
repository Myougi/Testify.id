<script lang="ts">
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import type {
        TestCaseFormValues,
        TestRepositoryModule,
        TestRepositoryOptions,
    } from '@/lib/api/testRepository';

    let {
        values = $bindable<TestCaseFormValues>(),
        options,
        errors = {},
        processing = false,
        title,
        description,
        submitLabel = 'Save Test Case',
        onsubmit,
        oncancel,
    }: {
        values: TestCaseFormValues;
        options: TestRepositoryOptions;
        errors?: Record<string, string>;
        processing?: boolean;
        title: string;
        description: string;
        submitLabel?: string;
        onsubmit: () => void;
        oncancel: () => void;
    } = $props();

    const moduleDepth = (
        module: TestRepositoryModule,
        modules: TestRepositoryModule[],
    ): number => {
        let depth = 0;
        let parentId = module.parent_id;

        while (parentId !== null) {
            const parent = modules.find((item) => item.id === parentId);

            if (!parent) {
                break;
            }

            depth += 1;
            parentId = parent.parent_id;
        }

        return depth;
    };

    const orderedModules = $derived(
        [...options.modules].sort((first, second) => {
            if (first.project_id !== second.project_id) {
                return first.project_id - second.project_id;
            }

            if ((first.parent_id ?? 0) !== (second.parent_id ?? 0)) {
                return (first.parent_id ?? 0) - (second.parent_id ?? 0);
            }

            return (
                first.sort_order - second.sort_order ||
                first.name.localeCompare(second.name)
            );
        }),
    );

    const addStep = (): void => {
        values.steps = [
            ...values.steps,
            {
                action: '',
                expected_result: '',
            },
        ];
    };

    const removeStep = (index: number): void => {
        values.steps = values.steps.filter(
            (_, stepIndex) => stepIndex !== index,
        );
    };
</script>

<div class="px-4 py-5 text-[#061b33] sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
        <div>
            <h1 class="text-3xl font-bold tracking-normal text-[#071a2f]">
                {title}
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">
                {description}
            </p>
        </div>

        <form
            class="grid gap-5"
            onsubmit={(event) => {
                event.preventDefault();
                onsubmit();
            }}
        >
            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="grid gap-5">
                    <div class="grid gap-2">
                        <Label for="test_case_title">Test Case Title</Label>
                        <Input
                            id="test_case_title"
                            bind:value={values.title}
                            required
                            class="h-11 border-slate-300 bg-slate-50 text-[#071a2f] placeholder:text-slate-400"
                            placeholder="e.g., Verify Secure OAuth Login Flow with 2FA"
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div
                        class="grid gap-5 lg:grid-cols-[1.5fr_0.75fr_0.9fr_0.9fr_1fr]"
                    >
                        <div class="grid gap-2">
                            <Label for="test_case_module"
                                >Folder / Module Path</Label
                            >
                            <select
                                id="test_case_module"
                                class="flex h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                                value={values.module_id ?? ''}
                                onchange={(event) => {
                                    const moduleId =
                                        Number(
                                            (
                                                event.currentTarget as HTMLSelectElement
                                            ).value,
                                        ) || null;
                                    values.module_id = moduleId;

                                    const module = options.modules.find(
                                        (item) => item.id === moduleId,
                                    );
                                    values.project_id =
                                        module?.project_id ?? values.project_id;
                                }}
                            >
                                <option value="">Select folder</option>
                                {#each orderedModules as module (module.id)}
                                    <option value={module.id}>
                                        {'- '.repeat(
                                            moduleDepth(
                                                module,
                                                options.modules,
                                            ),
                                        )}{module.name}
                                    </option>
                                {/each}
                            </select>
                            <InputError message={errors.module_id} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="test_case_priority">Priority</Label>
                            <select
                                id="test_case_priority"
                                bind:value={values.priority}
                                class="flex h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            >
                                {#each options.priorities as priority (priority)}
                                    <option value={priority}>{priority}</option>
                                {/each}
                            </select>
                            <InputError message={errors.priority} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="test_case_automation"
                                >Automation Status</Label
                            >
                            <select
                                id="test_case_automation"
                                bind:value={values.automation_status}
                                class="flex h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            >
                                {#each options.automationStatuses as status (status)}
                                    <option value={status}>{status}</option>
                                {/each}
                            </select>
                            <InputError message={errors.automation_status} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="test_case_behavior">Test Behavior</Label
                            >
                            <select
                                id="test_case_behavior"
                                bind:value={values.test_behavior}
                                class="flex h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            >
                                {#each options.behaviors as behavior (behavior)}
                                    <option value={behavior}>{behavior}</option>
                                {/each}
                            </select>
                            <InputError message={errors.test_behavior} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="test_case_type">Test Type</Label>
                            <select
                                id="test_case_type"
                                bind:value={values.test_type}
                                class="flex h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] outline-none focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                            >
                                {#each options.types as type (type)}
                                    <option value={type}>{type}</option>
                                {/each}
                            </select>
                            <InputError message={errors.test_type} />
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="grid gap-2">
                    <Label for="test_case_preconditions">Preconditions</Label>
                    <textarea
                        id="test_case_preconditions"
                        rows="5"
                        bind:value={values.preconditions}
                        class="flex min-h-32 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-3 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                        placeholder="List environment setup, user permissions, or data state required before starting this test..."
                    ></textarea>
                    <InputError message={errors.preconditions} />
                </div>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-5 py-4"
                >
                    <h2 class="text-sm font-semibold text-[#071a2f]">
                        Test Steps
                    </h2>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-[#002b57] text-white hover:bg-[#003c78]"
                        onclick={addStep}
                    >
                        <Plus class="size-4" />
                        Add Step
                    </Button>
                </div>

                <div class="grid gap-4 px-5 py-5">
                    {#if values.steps.length === 0}
                        <div
                            class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500"
                        >
                            No steps added yet. Add one if you want to capture
                            the workflow, or save the test case without steps.
                        </div>
                    {:else}
                        {#each values.steps as step, index (index)}
                            <div
                                class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[2.5rem_1fr_1fr_auto] md:items-start"
                            >
                                <div
                                    class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-500"
                                >
                                    {index + 1}
                                </div>
                                <div class="grid gap-2">
                                    <Label for={`test_step_action_${index}`}
                                        >Action</Label
                                    >
                                    <textarea
                                        id={`test_step_action_${index}`}
                                        rows="2"
                                        bind:value={step.action}
                                        class="flex min-h-20 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                                        placeholder="Step description"
                                    ></textarea>
                                    <InputError
                                        message={errors[
                                            `steps.${index}.action`
                                        ]}
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for={`test_step_expected_${index}`}>
                                        Expected Result
                                    </Label>
                                    <textarea
                                        id={`test_step_expected_${index}`}
                                        rows="2"
                                        bind:value={step.expected_result}
                                        class="flex min-h-20 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-[#071a2f] shadow-xs outline-none transition-[color,box-shadow] placeholder:text-slate-400 focus:border-[#002b57] focus:ring-3 focus:ring-[#002b57]/15"
                                        placeholder="What should happen?"
                                    ></textarea>
                                    <InputError
                                        message={errors[
                                            `steps.${index}.expected_result`
                                        ]}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                    aria-label="Remove step"
                                    onclick={() => removeStep(index)}
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        {/each}
                    {/if}
                    <InputError message={errors.steps} />
                </div>
            </section>

            <div
                class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end"
            >
                <Button type="button" variant="outline" onclick={oncancel}>
                    Cancel
                </Button>
                <Button
                    type="submit"
                    class="bg-[#002b57] px-6 text-white hover:bg-[#003c78]"
                    disabled={processing}
                >
                    {#if processing}<Spinner />{/if}
                    {submitLabel}
                </Button>
            </div>
        </form>
    </div>
</div>
