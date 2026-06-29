<script module lang="ts">
    import { index as testRepositoriesIndexRoute } from '@/routes/test-repositories';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Test Repositories',
                href: testRepositoriesIndexRoute(),
            },
            {
                title: 'Edit Test Case',
                href: '/test-repositories',
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
        TestRepositoryApiError,
        updateTestCase,
    } from '@/lib/api/testRepository';
    import type {
        TestCaseFormValues,
        TestCaseItem,
        TestRepositoryOptions,
    } from '@/lib/api/testRepository';

    let {
        testCase,
        options,
    }: {
        testCase: TestCaseItem;
        options: TestRepositoryOptions;
    } = $props();

    const createDefaultValues = (): TestCaseFormValues => ({
        project_id: testCase.project_id,
        module_id: testCase.module_id,
        title: testCase.title,
        priority: testCase.priority,
        automation_status: testCase.automation_status,
        test_behavior: testCase.test_behavior,
        test_type: testCase.test_type,
        preconditions: testCase.preconditions ?? '',
        steps:
            testCase.steps && testCase.steps.length > 0
                ? testCase.steps.map((step) => ({
                      action: step.action,
                      expected_result: step.expected_result,
                  }))
                : [],
    });

    let values = $state<TestCaseFormValues>(createDefaultValues());
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    const submit = async (): Promise<void> => {
        processing = true;
        errors = {};

        try {
            await updateTestCase(testCase.id, values);
            toast.success('Test case updated.');
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
                        : 'Unable to update the test case.',
                );
            }
        } finally {
            processing = false;
        }
    };
</script>

<AppHead title="Edit Test Case" />

<TestCaseForm
    bind:values
    {options}
    {errors}
    {processing}
    title="Edit Test Case"
    description="Update the scenario, behavior, type, and expected results for this repository test case."
    onsubmit={submit}
    oncancel={() => router.visit('/test-repositories')}
/>
