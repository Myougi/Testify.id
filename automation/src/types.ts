export type LocatorDefinition =
    | { by: 'role'; role: string; name?: string }
    | { by: 'label' | 'test_id' | 'text' | 'css'; value: string };

export type SecretReference = { profile: string; field: string };

export type AutomationStep =
    | { action: 'navigate' | 'assert_url'; path: string }
    | {
          action: 'click' | 'check' | 'uncheck' | 'assert_visible';
          locator: LocatorDefinition;
      }
    | {
          action: 'fill' | 'select';
          locator: LocatorDefinition;
          value?: string;
          secret?: SecretReference;
      }
    | { action: 'press'; locator: LocatorDefinition; key: string }
    | { action: 'wait'; milliseconds: number }
    | {
          action: 'assert_text' | 'assert_value';
          locator: LocatorDefinition;
          value: string;
      };

export interface AutomationDefinition {
    version: 1;
    steps: AutomationStep[];
}

export interface CredentialProfileMetadata {
    name: string;
    key: string;
    fields: string[];
}

export interface RunnerExecution {
    id: string;
    test_case_id: number;
    case_key: string;
    title: string;
    definition: AutomationDefinition | null;
    preconditions: string | null;
    manual_steps: Array<{
        position: number;
        action: string;
        expected_result: string;
    }>;
}

export interface AgenticGenerationConfig {
    goal_prompt: string | null;
    model: string;
    max_turns: number;
    timeout_seconds: number;
    snapshot_max_characters: number;
    source_hash: string;
}

export interface RunnerPayload {
    id: string;
    kind: 'Validation' | 'Execution' | 'Generation';
    environment: string;
    base_url: string;
    case_timeout_seconds: number;
    diagnostic_max_characters: number;
    generation: AgenticGenerationConfig | null;
    credential_profiles: CredentialProfileMetadata[];
    executions: RunnerExecution[];
}

export interface GenerationResult {
    success: boolean;
    definition?: AutomationDefinition;
    warnings: string[];
    failure_code?: string;
    failure_message?: string;
    duration_ms: number;
}

export type CredentialValues = Record<string, Record<string, string>>;

export interface ExecutionResult {
    status: 'Passed' | 'Failed' | 'Blocked' | 'Skipped';
    duration_ms: number;
    failure_type?: string;
    failure_code?:
        | 'locator_not_found'
        | 'locator_ambiguous'
        | 'assertion_failed'
        | 'navigation_failed'
        | 'step_timeout'
        | 'credential_error'
        | 'infrastructure_error';
    failure_message?: string;
    failed_step?: number;
    console_excerpt?: string;
}
