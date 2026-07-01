import { requestJson } from './client';

export type AiArtifactType =
    | 'test_plan'
    | 'test_cases'
    | 'issues'
    | 'report_summary';

export type AiReportFilters = {
    scope: string;
    range: string;
    start_date: string;
    end_date: string;
    grouping: 'daily' | 'weekly';
    sections: string[];
};

export type TestPlanAiDraft = {
    name: string;
    description: string | null;
    scope: string | null;
    strategy: string | null;
    criteria: string | null;
    status: 'Draft';
    priority: 'Low' | 'Medium' | 'High' | 'Urgent';
    start_date: string | null;
    due_date: string | null;
    release_version: string | null;
    tester: string | null;
};

export type TestCaseAiDraft = {
    module_id: number | null;
    title: string;
    priority: 'Low' | 'Medium' | 'High' | 'Urgent';
    automation_status: 'Manual' | 'Automated';
    test_behavior: 'Positive' | 'Negative';
    test_type:
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
    preconditions: string | null;
    steps: Array<{
        action: string;
        expected_result: string;
    }>;
};

export type TestCaseAiDraftSet = {
    cases: TestCaseAiDraft[];
};

export type IssueAiDraft = {
    title: string;
    description: string;
    expected_result: string | null;
    actual_result: string | null;
    priority: 'Low' | 'Medium' | 'High' | 'Urgent';
    severity: 'Low' | 'Medium' | 'High' | 'Critical';
    status: 'Open';
    reporter: string;
    assignee: string | null;
    environment:
        | 'Development'
        | 'SIT'
        | 'Staging'
        | 'UAT'
        | 'Production'
        | null;
    defect_type:
        | 'UI/UX'
        | 'Functional'
        | 'Compatibility'
        | 'Database'
        | 'Integration'
        | 'Performance'
        | 'Security'
        | 'Usability'
        | null;
    test_plan_id: number | null;
    test_run_id: number | null;
};

export type IssueAiDraftSet = {
    issues: IssueAiDraft[];
};

export type ReportSummaryAiDraft = {
    executive_summary: string;
    quality_risks: string[];
    coverage_notes: string[];
    issue_analysis: string[];
    recommendations: string[];
    next_actions: string[];
};

export type AiDraftResponse<TDraft> = {
    draft: TDraft;
    warnings: string[];
    source_context: Record<string, unknown>;
};

type AiDraftRequestBase = {
    prompt: string;
    project_id?: number | null;
    test_plan_id?: number | null;
    test_run_id?: number | null;
    module_id?: number | null;
    report_filters?: AiReportFilters | null;
};

export type AiDraftRequest = AiDraftRequestBase &
    (
        | {
              artifact_type: 'issues';
              count?: number | null;
          }
        | {
              artifact_type: Exclude<AiArtifactType, 'issues'>;
              count?: never;
          }
    );

export class AiDraftApiError extends Error {
    public status: number;

    public errors: Record<string, string>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string> = {},
    ) {
        super(message);

        this.name = 'AiDraftApiError';
        this.status = status;
        this.errors = errors;
    }
}

const AI_GENERATION_ENDPOINT = '/api/ai/generate';

const request = <T>(url: string, init: RequestInit = {}): Promise<T> =>
    requestJson<T, AiDraftApiError>(
        url,
        init,
        AiDraftApiError,
        'Unable to generate the AI draft.',
    );

export async function generateAiDraft<TDraft>(
    payload: AiDraftRequest,
): Promise<AiDraftResponse<TDraft>> {
    return request<AiDraftResponse<TDraft>>(AI_GENERATION_ENDPOINT, {
        body: JSON.stringify(payload),
        method: 'POST',
    });
}

export function summarizeAiSourceContext(
    sourceContext: Record<string, unknown>,
): string[] {
    const items: string[] = [];
    const project = sourceContext.project as
        | { name?: string | null }
        | undefined;
    const selectedPlan = sourceContext.selected_test_plan as
        | { name?: string | null }
        | undefined;
    const selectedRun = sourceContext.selected_test_run as
        | { name?: string | null }
        | undefined;
    const selectedModule = sourceContext.selected_module as
        | { name?: string | null; key_prefix?: string | null }
        | undefined;
    const reportFilters = sourceContext.report_filters as
        | { range?: string | null; grouping?: string | null }
        | undefined;
    const requestedCount =
        typeof sourceContext.requested_count === 'number'
            ? sourceContext.requested_count
            : null;
    const generatedCount =
        typeof sourceContext.generated_count === 'number'
            ? sourceContext.generated_count
            : null;

    if (project?.name) {
        items.push(`Project: ${project.name}`);
    }

    if (selectedPlan?.name) {
        items.push(`Plan: ${selectedPlan.name}`);
    }

    if (selectedRun?.name) {
        items.push(`Run: ${selectedRun.name}`);
    }

    if (selectedModule?.name) {
        items.push(
            selectedModule.key_prefix
                ? `Module: ${selectedModule.name} (${selectedModule.key_prefix})`
                : `Module: ${selectedModule.name}`,
        );
    }

    if (generatedCount !== null) {
        items.push(`Generated: ${generatedCount}`);
    } else if (requestedCount !== null && requestedCount > 1) {
        items.push(`Count: ${requestedCount}`);
    }

    if (reportFilters?.range) {
        items.push(`Range: ${reportFilters.range}`);
    }

    if (reportFilters?.grouping) {
        items.push(`Grouping: ${reportFilters.grouping}`);
    }

    return items;
}
