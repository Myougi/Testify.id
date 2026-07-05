export type IssuePriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type IssueSeverity = 'Low' | 'Medium' | 'High' | 'Critical';

export type IssueStatus =
    | 'Open'
    | 'In Investigation'
    | 'In Testing'
    | 'Solved'
    | 'Deferred';

export type IssueEnvironment =
    | 'Development'
    | 'SIT'
    | 'Staging'
    | 'UAT'
    | 'Production';

export type IssueDefectType =
    | 'UI/UX'
    | 'Functional'
    | 'Compatibility'
    | 'Database'
    | 'Integration'
    | 'Performance'
    | 'Security'
    | 'Usability';

export type IssuePlanOption = {
    id: number;
    name: string;
};

export type IssueRunOption = {
    id: number;
    name: string;
    status: string;
    test_plan: {
        id: number;
        name: string | null;
    };
};

export type IssueImage = {
    id: number;
    name: string;
    mime_type: string;
    size: number;
    url: string;
};

export type IssueItem = {
    id: number;
    issue_key: string;
    title: string | null;
    description: string;
    expected_result: string | null;
    actual_result: string | null;
    priority: IssuePriority;
    severity: IssueSeverity;
    status: IssueStatus;
    reporter: string;
    assignee: string | null;
    environment: string | null;
    defect_type: string | null;
    image: IssueImage | null;
    project: {
        id: number;
        name: string | null;
    };
    test_plan: {
        id: number | null;
        name: string | null;
    };
    test_run: {
        id: number | null;
        name: string | null;
        status: string | null;
        test_plan: {
            id: number | null;
            name: string | null;
        };
    };
    closed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type IssueStats = {
    totalActiveIssues: number;
    criticalIssues: number;
    resolutionVelocityDays: number | null;
};

export type IssueFilters = {
    search: string;
    severity: string;
    status: string;
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

export type IssueListResponse = {
    data: IssueItem[];
    meta: PaginationMeta;
    stats: IssueStats;
    filters: IssueFilters;
    options: {
        hasActiveProject: boolean;
        testPlans: IssuePlanOption[];
        testRuns: IssueRunOption[];
        priorities: IssuePriority[];
        severities: IssueSeverity[];
        statuses: IssueStatus[];
        environments: IssueEnvironment[];
        defectTypes: IssueDefectType[];
    };
};

export type IssueFormValues = {
    title: string;
    description: string;
    expected_result: string;
    actual_result: string;
    priority: IssuePriority;
    severity: IssueSeverity;
    status: IssueStatus;
    reporter: string;
    assignee: string;
    environment: IssueEnvironment | '';
    defect_type: IssueDefectType | '';
    test_plan_id: number | null;
    test_run_id: number | null;
};

export class IssueApiError extends Error {
    public status: number;

    public errors: Record<string, string>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string> = {},
        public code: string | null = null,
    ) {
        super(message);

        this.name = 'IssueApiError';
        this.status = status;
        this.errors = errors;
    }
}

const ISSUES_ENDPOINT = '/api/issues';

const request = <T>(url: string, init: RequestInit = {}): Promise<T> =>
    requestJson<T, IssueApiError>(
        url,
        init,
        IssueApiError,
        'Unable to process the issue request.',
    );

const serializePayload = (values: IssueFormValues) => ({
    title: values.title.trim(),
    description: values.description.trim(),
    expected_result:
        values.expected_result.trim() === ''
            ? null
            : values.expected_result.trim(),
    actual_result:
        values.actual_result.trim() === '' ? null : values.actual_result.trim(),
    priority: values.priority,
    severity: values.severity,
    status: values.status,
    reporter: values.reporter.trim(),
    assignee: values.assignee.trim() === '' ? null : values.assignee.trim(),
    environment:
        values.environment.trim() === '' ? null : values.environment.trim(),
    defect_type:
        values.defect_type.trim() === '' ? null : values.defect_type.trim(),
    test_plan_id: values.test_plan_id,
    test_run_id: values.test_run_id,
});

const toFormData = (
    values: IssueFormValues,
    image: File | null,
    removeImage = false,
): FormData => {
    const formData = new FormData();
    const payload = serializePayload(values);

    Object.entries(payload).forEach(([key, value]) => {
        formData.append(key, value === null ? '' : String(value));
    });

    if (image) {
        formData.append('image', image);
    }

    if (removeImage) {
        formData.append('remove_image', '1');
    }

    return formData;
};

export async function fetchIssues(
    filters: Partial<IssueFilters>,
): Promise<IssueListResponse> {
    return request<IssueListResponse>(
        `${ISSUES_ENDPOINT}${buildQueryString(filters)}`,
    );
}

export async function createIssue(
    values: IssueFormValues,
    image: File | null = null,
): Promise<{ data: IssueItem }> {
    if (image) {
        return requestFormData<{ data: IssueItem }, IssueApiError>(
            ISSUES_ENDPOINT,
            toFormData(values, image),
            IssueApiError,
            'Unable to create the issue.',
        );
    }

    return request<{ data: IssueItem }>(ISSUES_ENDPOINT, {
        body: JSON.stringify(serializePayload(values)),
        method: 'POST',
    });
}

export async function updateIssue(
    issueId: number,
    values: IssueFormValues,
    imageOptions: {
        image?: File | null;
        removeImage?: boolean;
    } = {},
): Promise<{ data: IssueItem }> {
    if (imageOptions.image || imageOptions.removeImage) {
        const formData = toFormData(
            values,
            imageOptions.image ?? null,
            imageOptions.removeImage ?? false,
        );
        formData.append('_method', 'PUT');

        return requestFormData<{ data: IssueItem }, IssueApiError>(
            `${ISSUES_ENDPOINT}/${issueId}`,
            formData,
            IssueApiError,
            'Unable to update the issue.',
        );
    }

    return request<{ data: IssueItem }>(`${ISSUES_ENDPOINT}/${issueId}`, {
        body: JSON.stringify(serializePayload(values)),
        method: 'PUT',
    });
}

export async function deleteIssue(
    issueId: number,
): Promise<{ message: string }> {
    return request<{ message: string }>(`${ISSUES_ENDPOINT}/${issueId}`, {
        method: 'DELETE',
    });
}
import { buildQueryString, requestFormData, requestJson } from './client';
