export type TestRunEnvironment =
    | 'Development'
    | 'SIT'
    | 'Staging'
    | 'UAT'
    | 'Production';

export type TestRunPriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type TestRunStatus = 'New' | 'In Progress' | 'Completed';

export type TestRunCaseStatus =
    | 'Not Run'
    | 'Passed'
    | 'Failed'
    | 'In Testing'
    | 'Skipped'
    | 'Blocked';

export type TestRunPlanCase = {
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

export type TestRunCase = TestRunPlanCase & {
    execution_status: TestRunCaseStatus;
};

export type TestRunPlanOption = {
    id: number;
    name: string;
    project: {
        id: number;
        name: string | null;
    };
    test_cases: TestRunPlanCase[];
};

export type TestRunExecutionSummary = {
    not_run: number;
    passed: number;
    failed: number;
    in_testing: number;
    skipped: number;
    blocked: number;
    completed: number;
    total: number;
    progress: number;
};

export type TestRunItem = {
    id: number;
    name: string;
    environment: TestRunEnvironment;
    priority: TestRunPriority;
    description: string | null;
    tester_name: string;
    status: TestRunStatus;
    test_plan: {
        id: number;
        name: string | null;
        project: {
            id: number;
            name: string | null;
        };
    };
    assigned_test_cases_count?: number;
    execution_summary: TestRunExecutionSummary;
    test_cases?: TestRunCase[];
    execution_started_at: string | null;
    execution_completed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type TestRunStats = {
    totalRuns: number;
    newRuns: number;
    activeRuns: number;
    doneRuns: number;
};

export type TestRunFilters = {
    search: string;
    status: string;
    environment: string;
    priority: string;
    test_plan_id: number | null;
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

export type TestRunListResponse = {
    data: TestRunItem[];
    meta: PaginationMeta;
    stats: TestRunStats;
    filters: TestRunFilters;
    options: {
        testPlans: TestRunPlanOption[];
        environments: TestRunEnvironment[];
        priorities: TestRunPriority[];
        statuses: TestRunStatus[];
        caseStatuses?: TestRunCaseStatus[];
    };
};

export type TestPlanRunsResponse = {
    data: TestRunItem[];
    meta: PaginationMeta;
};

export type TestRunFormValues = {
    test_plan_id: number | null;
    test_case_ids: number[];
    name: string;
    environment: TestRunEnvironment;
    priority: TestRunPriority;
    description: string;
    tester_name: string;
};

export class TestRunApiError extends Error {
    public status: number;

    public errors: Record<string, string>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string> = {},
        public code: string | null = null,
    ) {
        super(message);

        this.name = 'TestRunApiError';
        this.status = status;
        this.errors = errors;
    }
}

const TEST_RUNS_ENDPOINT = '/api/test-runs';

const request = <T>(url: string, init: RequestInit = {}): Promise<T> =>
    requestJson<T, TestRunApiError>(
        url,
        init,
        TestRunApiError,
        'Unable to process the test run request.',
    );

const serializePayload = (values: TestRunFormValues) => ({
    test_plan_id: values.test_plan_id,
    test_case_ids: values.test_case_ids,
    name: values.name.trim(),
    environment: values.environment,
    priority: values.priority,
    description:
        values.description.trim() === '' ? null : values.description.trim(),
    tester_name: values.tester_name.trim(),
});

export async function fetchTestRuns(
    filters: Partial<TestRunFilters>,
): Promise<TestRunListResponse> {
    return request<TestRunListResponse>(
        `${TEST_RUNS_ENDPOINT}${buildQueryString(filters)}`,
    );
}

export async function createTestRun(
    values: TestRunFormValues,
): Promise<{ data: TestRunItem }> {
    return request<{ data: TestRunItem }>(TEST_RUNS_ENDPOINT, {
        body: JSON.stringify(serializePayload(values)),
        method: 'POST',
    });
}

export async function updateTestRun(
    testRunId: number,
    values: TestRunFormValues,
): Promise<{ data: TestRunItem }> {
    return request<{ data: TestRunItem }>(
        `${TEST_RUNS_ENDPOINT}/${testRunId}`,
        {
            body: JSON.stringify(serializePayload(values)),
            method: 'PUT',
        },
    );
}

export async function startTestRun(
    testRunId: number,
): Promise<{ data: TestRunItem }> {
    return request<{ data: TestRunItem }>(
        `${TEST_RUNS_ENDPOINT}/${testRunId}/start`,
        {
            method: 'PATCH',
        },
    );
}

export async function deleteTestRun(
    testRunId: number,
): Promise<{ message: string }> {
    return request<{ message: string }>(`${TEST_RUNS_ENDPOINT}/${testRunId}`, {
        method: 'DELETE',
    });
}

export async function updateTestRunCaseStatus(
    testRunId: number,
    testCaseId: number,
    status: TestRunCaseStatus,
): Promise<{ data: TestRunItem }> {
    return request<{ data: TestRunItem }>(
        `${TEST_RUNS_ENDPOINT}/${testRunId}/test-cases/${testCaseId}/status`,
        {
            body: JSON.stringify({ status }),
            method: 'PATCH',
        },
    );
}
import { buildQueryString, requestJson } from './client';
