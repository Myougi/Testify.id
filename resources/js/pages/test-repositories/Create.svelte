<script module lang="ts">
    import { index as testRepositoriesIndexRoute } from '@/routes/test-repositories';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Test Repositories',
                href: testRepositoriesIndexRoute(),
            },
            {
                title: 'Create Test Case',
                href: '/test-repositories/test-cases/create',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import TestCaseForm from '@/components/test-repositories/TestCaseForm.svelte';
    import {
        createTestCase,
        TestRepositoryApiError,
    } from '@/lib/api/testRepository';
    import type {
        TestCaseFormValues,
        TestRepositoryOptions,
    } from '@/lib/api/testRepository';

    let {
        options,
        selectedModuleId,
    }: {
        options: TestRepositoryOptions;
        selectedModuleId: number | null;
    } = $props();

    const createDefaultValues = (): TestCaseFormValues => {
        const selectedModule =
            options.modules.find((module) => module.id === selectedModuleId) ??
            options.modules[0];

        return {
            project_id:
                selectedModule?.project_id ?? options.projects[0]?.id ?? null,
            module_id: selectedModule?.id ?? null,
            title: '',
            priority:
                options.priorities.find((priority) => priority === 'Medium') ??
                options.priorities[0] ??
                'Medium',
            automation_status: options.automationStatuses[0] ?? 'Manual',
            test_behavior: options.behaviors[0] ?? 'Positive',
            test_type: options.types[0] ?? 'Functional',
            preconditions: '',
            steps: [],
        };
    };

    let values = $state<TestCaseFormValues>(createDefaultValues());
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    const submit = async (): Promise<void> => {
        processing = true;
        errors = {};

        try {
            await createTestCase(values);
            toast.success('Test case saved.');
            router.visit('/test-repositories');
        } catch (error) {
            if (
                error instanceof TestRepositoryApiError &&
                error.status === 422
            ) {
                errors = error.errors;
            } else {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Unable to save the test case.',
                );
            }
        } finally {
            processing = false;
        }
    };
</script>

<AppHead title="Create Test Case" />

<TestCaseForm
    bind:values
    {options}
    {errors}
    {processing}
    title="Create New Test Case"
    description="Define test scenarios with rigorous detail to ensure maximum system reliability."
    onsubmit={submit}
    oncancel={() => router.visit('/test-repositories')}
/>
