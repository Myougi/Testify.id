import { errors } from 'playwright';
import { CredentialConfigurationError } from './credentials.js';
import {
    AssertionFailure,
    StepExecutionFailure,
} from './interpreter.js';
import type { ExecutionResult } from './types.js';

export function classifyFailure(
    error: unknown,
    message: string,
    durationMs: number,
): ExecutionResult {
    if (error instanceof AssertionFailure) {
        return {
            status: 'Failed',
            duration_ms: durationMs,
            failure_type: 'assertion',
            failure_code: 'assertion_failed',
            failure_message: message,
            failed_step: error.stepNumber,
        };
    }

    if (error instanceof StepExecutionFailure) {
        const isTimeout =
            error.cause instanceof errors.TimeoutError ||
            /timeout|timed?\s+out/i.test(message);
        const isAmbiguous = message.toLowerCase().includes('strict mode violation');
        const failureCode =
            error.action === 'navigate'
                ? 'navigation_failed'
                : isAmbiguous
                  ? 'locator_ambiguous'
                  : isTimeout && error.action !== 'wait'
                    ? 'locator_not_found'
                    : 'step_timeout';

        return {
            status: 'Blocked',
            duration_ms: durationMs,
            failure_type: 'automation',
            failure_code: failureCode,
            failure_message: message,
            failed_step: error.stepNumber,
        };
    }

    if (error instanceof CredentialConfigurationError) {
        return {
            status: 'Blocked',
            duration_ms: durationMs,
            failure_type: 'credential',
            failure_code: 'credential_error',
            failure_message: message,
        };
    }

    if (
        error instanceof errors.TimeoutError ||
        message === 'The case exceeded its configured timeout.'
    ) {
        return {
            status: 'Blocked',
            duration_ms: durationMs,
            failure_type: 'infrastructure',
            failure_code: 'infrastructure_error',
            failure_message: message,
        };
    }

    return {
        status: 'Blocked',
        duration_ms: durationMs,
        failure_type: 'infrastructure',
        failure_code: 'infrastructure_error',
        failure_message: message,
    };
}
