import { buildQueryString, requestJson } from './client';

export type TestCasePriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type TestCaseAutomationStatus = 'Manual' | 'Automated';

export type TestCaseBehavior = 'Positive' | 'Negative';

export type TestCaseType =
    | 'Acceptance'
    | 'Accessibility'
    | 'Compatibility'
    | 'Functional'
    | 'Integration'
    | 'Performance'
    | 'Regression'
    | 'UI/UX'
    | 'Security'
    | 'Sanity'
    | 'Smoke';

export type TestRepositoryProjectOption = {
    id: number;
    name: string;
};

export type TestRepositoryPlanOption = {
    id: number;
    name: string;
};

export type TestRepositoryModule = {
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
};

export type TestCaseStep = {
    id?: number;
    position: number;
    action: string;
    expected_result: string;
};

export type TestCaseItem = {
    id: number;
    project_id: number;
    module_id: number;
    case_key: string;
    title: string;
    priority: TestCasePriority;
    automation_status: TestCaseAutomationStatus;
    test_behavior: TestCaseBehavior;
    test_type: TestCaseType;
    preconditions: string | null;
    project: TestRepositoryProjectOption;
    module: {
        id: number;
        project_id: number;
        parent_id: number | null;
        name: string;
        key_prefix: string;
    };
    steps?: TestCaseStep[];
    created_at: string | null;
    updated_at: string | null;
};

export type TestRepositoryStats = {
    totalCases: number;
    automatedCases: number;
    manualCases: number;
    positiveCases: number;
    negativeCases: number;
};

export type TestRepositoryFilters = {
    search: string;
    module_id: number | null;
    priority: string;
    automation_status: string;
    test_behavior: string;
    test_type: string;
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

export type TestRepositoryOptions = {
    projects: TestRepositoryProjectOption[];
    modules: TestRepositoryModule[];
    priorities: TestCasePriority[];
    automationStatuses: TestCaseAutomationStatus[];
    behaviors: TestCaseBehavior[];
    types: TestCaseType[];
    aiGeneration: {
        generationLimit: number;
        hasActiveProject: boolean;
        activeProject: TestRepositoryProjectOption | null;
        testPlans: TestRepositoryPlanOption[];
        modules: TestRepositoryModule[];
    };
};

export type TestCaseListResponse = {
    data: TestCaseItem[];
    meta: PaginationMeta;
    stats: TestRepositoryStats;
    filters: TestRepositoryFilters;
    options: TestRepositoryOptions;
};

export type TestRepositoryModuleFormValues = {
    project_id: number | null;
    parent_id: number | null;
    name: string;
    key_prefix: string;
};

export type TestCaseFormValues = {
    project_id: number | null;
    module_id: number | null;
    title: string;
    priority: TestCasePriority;
    automation_status: TestCaseAutomationStatus;
    test_behavior: TestCaseBehavior;
    test_type: TestCaseType;
    preconditions: string;
    steps: Array<{
        action: string;
        expected_result: string;
    }>;
};

export type BulkCreateTestCasesPayload = {
    project_id: number;
    module_id: number;
    test_plan_id: number;
    cases: TestCaseFormValues[];
};

export type BulkCreateTestCasesResponse = {
    data: TestCaseItem[];
    meta: {
        created: number;
        test_plan: TestRepositoryPlanOption;
        module: {
            id: number;
            name: string;
        } | null;
    };
};

export class TestRepositoryApiError extends Error {
    public status: number;

    public errors: Record<string, string>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string> = {},
        public code: string | null = null,
    ) {
        super(message);

        this.name = 'TestRepositoryApiError';
        this.status = status;
        this.errors = errors;
    }
}

const TEST_CASES_ENDPOINT = '/api/test-cases';
const TEST_MODULES_ENDPOINT = '/api/test-repository-modules';

const request = <T>(url: string, init: RequestInit = {}): Promise<T> =>
    requestJson<T, TestRepositoryApiError>(
        url,
        init,
        TestRepositoryApiError,
        'Unable to process the test repository request.',
    );

const serializeModulePayload = (values: TestRepositoryModuleFormValues) => ({
    project_id: values.project_id,
    parent_id: values.parent_id,
    name: values.name.trim(),
    key_prefix: values.key_prefix.trim().toUpperCase(),
});

const serializeCasePayload = (values: TestCaseFormValues) => ({
    project_id: values.project_id,
    module_id: values.module_id,
    title: values.title.trim(),
    priority: values.priority,
    automation_status: values.automation_status,
    test_behavior: values.test_behavior,
    test_type: values.test_type,
    preconditions:
        values.preconditions.trim() === '' ? null : values.preconditions.trim(),
    steps: values.steps.map((step) => ({
        action: step.action.trim(),
        expected_result: step.expected_result.trim(),
    })),
});

export async function fetchTestCases(
    filters: Partial<TestRepositoryFilters>,
): Promise<TestCaseListResponse> {
    return request<TestCaseListResponse>(
        `${TEST_CASES_ENDPOINT}${buildQueryString(filters)}`,
    );
}

export async function createTestCase(
    values: TestCaseFormValues,
): Promise<{ data: TestCaseItem }> {
    return request<{ data: TestCaseItem }>(TEST_CASES_ENDPOINT, {
        body: JSON.stringify(serializeCasePayload(values)),
        method: 'POST',
    });
}

export async function bulkCreateTestCases(
    payload: BulkCreateTestCasesPayload,
): Promise<BulkCreateTestCasesResponse> {
    return request<BulkCreateTestCasesResponse>(`${TEST_CASES_ENDPOINT}/bulk`, {
        body: JSON.stringify({
            project_id: payload.project_id,
            module_id: payload.module_id,
            test_plan_id: payload.test_plan_id,
            cases: payload.cases.map((values) => serializeCasePayload(values)),
        }),
        method: 'POST',
    });
}

export async function updateTestCase(
    testCaseId: number,
    values: TestCaseFormValues,
): Promise<{ data: TestCaseItem }> {
    return request<{ data: TestCaseItem }>(
        `${TEST_CASES_ENDPOINT}/${testCaseId}`,
        {
            body: JSON.stringify(serializeCasePayload(values)),
            method: 'PUT',
        },
    );
}

export async function deleteTestCase(
    testCaseId: number,
): Promise<{ message: string }> {
    return request<{ message: string }>(
        `${TEST_CASES_ENDPOINT}/${testCaseId}`,
        {
            method: 'DELETE',
        },
    );
}

export async function createTestRepositoryModule(
    values: TestRepositoryModuleFormValues,
): Promise<{ data: TestRepositoryModule }> {
    return request<{ data: TestRepositoryModule }>(TEST_MODULES_ENDPOINT, {
        body: JSON.stringify(serializeModulePayload(values)),
        method: 'POST',
    });
}

export async function updateTestRepositoryModule(
    moduleId: number,
    values: TestRepositoryModuleFormValues,
): Promise<{ data: TestRepositoryModule }> {
    return request<{ data: TestRepositoryModule }>(
        `${TEST_MODULES_ENDPOINT}/${moduleId}`,
        {
            body: JSON.stringify(serializeModulePayload(values)),
            method: 'PUT',
        },
    );
}

export async function deleteTestRepositoryModule(
    moduleId: number,
): Promise<{ message: string }> {
    return request<{ message: string }>(
        `${TEST_MODULES_ENDPOINT}/${moduleId}`,
        {
            method: 'DELETE',
        },
    );
}
