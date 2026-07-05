import { requestJson } from './client';

export type AutomationEnvironment =
    | 'Development'
    | 'SIT'
    | 'Staging'
    | 'UAT'
    | 'Production';

export type AutomationDefinition = {
    version: 1;
    steps: Array<Record<string, unknown>>;
};

export type AutomationSettings = {
    enabled: boolean;
    environments: Array<{
        environment: AutomationEnvironment;
        base_url: string;
        enabled: boolean;
    }>;
    credential_profiles: Array<{
        id?: number;
        environment: AutomationEnvironment;
        name: string;
        key: string;
        fields: string[];
    }>;
};

export type TestCaseAutomation = {
    id: number;
    driver: string;
    schema_version: number;
    status: 'Draft' | 'Validating' | 'Healing' | 'Ready' | 'Invalid';
    definition: AutomationDefinition;
    source_hash: string;
    generated_at: string | null;
    last_validated_environment: AutomationEnvironment | null;
    last_validated_at: string | null;
    validation_summary: string | null;
    approved_by: string | null;
    approval_source: 'User' | 'AI Healed' | null;
    approved_at: string | null;
    last_healed_at: string | null;
    healing_history: AutomationHealingAttempt[];
};

export type AutomationHealingAttempt = {
    id: string;
    status: 'Healing' | 'Validating' | 'Applied' | 'Failed' | 'Cancelled';
    failed_step: number;
    original_definition: AutomationDefinition;
    proposed_definition: AutomationDefinition | null;
    rationale: string | null;
    confidence: number | null;
    failure_message: string | null;
    source_execution_id: string;
    revalidation_run_id: string | null;
    created_at: string | null;
    completed_at: string | null;
};

export type AutomationCasePayload = {
    test_case_id: number;
    automation_status: string;
    automation: TestCaseAutomation | null;
    settings: AutomationSettings;
};

export type AutomationArtifact = {
    id: number;
    type: 'Screenshot' | 'Trace' | 'Log' | 'Diagnostic';
    name: string;
};

export type AutomationExecution = {
    id: string;
    test_case_id: number;
    case_key: string;
    status: 'Queued' | 'Running' | 'Passed' | 'Failed' | 'Blocked' | 'Skipped';
    duration_ms: number | null;
    failure_type: string | null;
    failure_code: string | null;
    failure_message: string | null;
    failed_step: number | null;
    artifacts: AutomationArtifact[];
};

export type AutomationRun = {
    id: string;
    kind: 'Validation' | 'Execution';
    attempt: number;
    environment: AutomationEnvironment;
    status:
        | 'Queued'
        | 'Dispatching'
        | 'Running'
        | 'Completed'
        | 'Failed'
        | 'Error'
        | 'Cancelled';
    provider_run_url: string | null;
    failure_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    executions: AutomationExecution[];
};

export class AutomationApiError extends Error {
    public constructor(
        message: string,
        public status: number,
        public errors: Record<string, string> = {},
        public code: string | null = null,
    ) {
        super(message);
        this.name = 'AutomationApiError';
    }
}

const request = <T>(url: string, init: RequestInit = {}): Promise<T> =>
    requestJson<T, AutomationApiError>(
        url,
        init,
        AutomationApiError,
        'Unable to process the automation request.',
    );

export const fetchAutomationSettings = (
    projectId: number,
): Promise<AutomationSettings> =>
    request(`/api/projects/${projectId}/automation-settings`);

export const updateAutomationSettings = (
    projectId: number,
    settings: AutomationSettings,
): Promise<AutomationSettings> =>
    request(`/api/projects/${projectId}/automation-settings`, {
        method: 'PUT',
        body: JSON.stringify(settings),
    });

export const fetchTestCaseAutomation = (
    testCaseId: number,
): Promise<{ data: AutomationCasePayload }> =>
    request(`/api/test-cases/${testCaseId}/automation`);

export const generateTestCaseAutomation = (
    testCaseId: number,
): Promise<{ data: AutomationCasePayload; warnings: string[] }> =>
    request(`/api/test-cases/${testCaseId}/automation/generate`, {
        method: 'POST',
    });

export const updateTestCaseAutomation = (
    testCaseId: number,
    definition: AutomationDefinition,
): Promise<{ data: AutomationCasePayload }> =>
    request(`/api/test-cases/${testCaseId}/automation`, {
        method: 'PUT',
        body: JSON.stringify({ definition }),
    });

export const validateTestCaseAutomation = (
    testCaseId: number,
    environment: AutomationEnvironment,
): Promise<{ data: AutomationRun }> =>
    request(`/api/test-cases/${testCaseId}/automation/validate`, {
        method: 'POST',
        body: JSON.stringify({ environment }),
    });

export const approveTestCaseAutomation = (
    testCaseId: number,
): Promise<{ data: AutomationCasePayload }> =>
    request(`/api/test-cases/${testCaseId}/automation/approve`, {
        method: 'POST',
    });

export const fetchAutomationRuns = (
    testRunId: number,
): Promise<{ data: AutomationRun[] }> =>
    request(`/api/test-runs/${testRunId}/automation-runs`);

export const retryAutomationRun = (
    testRunId: number,
): Promise<{ data: AutomationRun }> =>
    request(`/api/test-runs/${testRunId}/automation-runs/retry`, {
        method: 'POST',
    });

export const generateAutomationIssueDraft = <T>(
    executionId: string,
): Promise<T> =>
    request(`/api/automation-executions/${executionId}/issue-draft`, {
        method: 'POST',
    });
