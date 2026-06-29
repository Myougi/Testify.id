import { exportMethod as testPlanExport } from '@/routes/test-plans';
import { buildQueryString, requestFile, requestJson } from './client';
import type { FileDownload } from './client';

export type TestPlanStatus = 'Draft' | 'In Progress' | 'Completed' | 'Archived';

export type TestPlanPriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type TestPlanOption = {
    id: number;
    name: string;
};

export type AssignedTestPlanCase = {
    id: number;
    case_key: string;
    title: string;
    priority: string;
    automation_status: string;
    test_behavior: string;
    test_type: string;
    module: {
        id: number;
        name: string | null;
    };
};

export type TestPlanItem = {
    id: number;
    name: string;
    description: string | null;
    scope: string | null;
    strategy: string | null;
    criteria: string | null;
    status: TestPlanStatus;
    priority: TestPlanPriority;
    start_date: string | null;
    due_date: string | null;
    release_version: string | null;
    tester: string | null;
    project: TestPlanOption;
    assigned_test_cases_count?: number;
    test_cases?: AssignedTestPlanCase[];
    created_at: string | null;
    updated_at: string | null;
};

export type TestPlanStats = {
    totalPlans: number;
    activePlans: number;
    completedPlans: number;
    overduePlans: number;
};

export type TestPlanFilters = {
    search: string;
    status: string;
    priority: string;
    per_page: number;
    page: number;
};

export type PaginationMetaLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationMetaLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

export type TestPlanListResponse = {
    data: TestPlanItem[];
    meta: PaginationMeta;
    stats: TestPlanStats;
    filters: TestPlanFilters;
    options: {
        projects: TestPlanOption[];
        statuses: TestPlanStatus[];
        priorities: TestPlanPriority[];
        selectedProjectId: number | null;
    };
};

export type AvailableCaseFilters = {
    search: string;
    module_id: number | null;
    priority: string;
    automation_status: string;
    test_behavior: string;
    test_type: string;
    per_page: number;
    page: number;
};

export type AvailableTestCase = {
    id: number;
    case_key: string;
    title: string;
    priority: string;
    automation_status: string;
    test_behavior: string;
    test_type: string;
    module: {
        id: number;
        name: string | null;
        key_prefix: string;
        parent_id: number | null;
        project_id: number;
    };
    project: TestPlanOption;
    created_at: string | null;
    updated_at: string | null;
};

export type AvailableTestCasesResponse = {
    data: AvailableTestCase[];
    meta: PaginationMeta;
    filters: AvailableCaseFilters;
    options: {
        modules: Array<{
            id: number;
            project_id: number;
            parent_id: number | null;
            name: string;
            key_prefix: string;
            sort_order: number;
            children_count?: number;
            test_cases_count?: number;
            created_at: string | null;
            updated_at: string | null;
        }>;
        priorities: string[];
        automationStatuses: string[];
        behaviors: string[];
        types: string[];
    };
};

export type TestPlanFormValues = {
    project_id: number | null;
    name: string;
    description: string;
    scope: string;
    strategy: string;
    criteria: string;
    status: TestPlanStatus;
    priority: TestPlanPriority;
    start_date: string;
    due_date: string;
    release_version: string;
    tester: string;
};

export class TestPlanApiError extends Error {
    public status: number;

    public errors: Record<string, string>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string> = {},
    ) {
        super(message);

        this.name = 'TestPlanApiError';
        this.status = status;
        this.errors = errors;
    }
}

const TEST_PLANS_ENDPOINT = '/api/test-plans';

const request = <T>(url: string, init: RequestInit = {}): Promise<T> =>
    requestJson<T, TestPlanApiError>(
        url,
        init,
        TestPlanApiError,
        'Unable to process the test plan request.',
    );

const serializePayload = (values: TestPlanFormValues) => ({
    project_id: values.project_id,
    name: values.name,
    description:
        values.description.trim() === '' ? null : values.description.trim(),
    scope: values.scope.trim() === '' ? null : values.scope.trim(),
    strategy: values.strategy.trim() === '' ? null : values.strategy.trim(),
    criteria: values.criteria.trim() === '' ? null : values.criteria.trim(),
    status: values.status,
    priority: values.priority,
    start_date: values.start_date === '' ? null : values.start_date,
    due_date: values.due_date === '' ? null : values.due_date,
    release_version:
        values.release_version.trim() === ''
            ? null
            : values.release_version.trim(),
    tester: values.tester.trim() === '' ? null : values.tester.trim(),
});

export async function fetchTestPlans(
    filters: Partial<TestPlanFilters>,
): Promise<TestPlanListResponse> {
    return request<TestPlanListResponse>(
        `${TEST_PLANS_ENDPOINT}${buildQueryString(filters)}`,
    );
}

export async function createTestPlan(
    values: TestPlanFormValues,
): Promise<{ data: TestPlanItem }> {
    return request<{ data: TestPlanItem }>(TEST_PLANS_ENDPOINT, {
        body: JSON.stringify(serializePayload(values)),
        method: 'POST',
    });
}

export async function updateTestPlan(
    testPlanId: number,
    values: TestPlanFormValues,
): Promise<{ data: TestPlanItem }> {
    return request<{ data: TestPlanItem }>(
        `${TEST_PLANS_ENDPOINT}/${testPlanId}`,
        {
            body: JSON.stringify(serializePayload(values)),
            method: 'PUT',
        },
    );
}

export async function deleteTestPlan(
    testPlanId: number,
): Promise<{ message: string }> {
    return request<{ message: string }>(
        `${TEST_PLANS_ENDPOINT}/${testPlanId}`,
        {
            method: 'DELETE',
        },
    );
}

export async function fetchTestPlan(
    testPlanId: number,
): Promise<{ data: TestPlanItem }> {
    return request<{ data: TestPlanItem }>(
        `${TEST_PLANS_ENDPOINT}/${testPlanId}`,
    );
}

export async function fetchAvailableTestCases(
    testPlanId: number,
    filters: Partial<AvailableCaseFilters>,
): Promise<AvailableTestCasesResponse> {
    return request<AvailableTestCasesResponse>(
        `${TEST_PLANS_ENDPOINT}/${testPlanId}/available-test-cases${buildQueryString(
            filters as Record<string, string | number | null | undefined>,
        )}`,
    );
}

export async function attachTestCasesToPlan(
    testPlanId: number,
    testCaseIds: number[],
): Promise<{ data: TestPlanItem; message: string }> {
    return request<{ data: TestPlanItem; message: string }>(
        `${TEST_PLANS_ENDPOINT}/${testPlanId}/test-cases`,
        {
            body: JSON.stringify({
                test_case_ids: testCaseIds,
            }),
            method: 'POST',
        },
    );
}

export async function detachTestCaseFromPlan(
    testPlanId: number,
    testCaseId: number,
): Promise<{ data: TestPlanItem; message: string }> {
    return request<{ data: TestPlanItem; message: string }>(
        `${TEST_PLANS_ENDPOINT}/${testPlanId}/test-cases/${testCaseId}`,
        {
            method: 'DELETE',
        },
    );
}

export async function exportTestPlanPdf(
    testPlanId: number,
): Promise<FileDownload> {
    return requestFile(
        testPlanExport(testPlanId).url,
        {
            method: 'GET',
        },
        TestPlanApiError,
        'Unable to export the test plan PDF.',
    );
}
