import assert from 'node:assert/strict';
import test from 'node:test';
import { sanitizeAriaSnapshot } from '../src/diagnostics.js';
import { classifyFailure } from '../src/failures.js';
import {
    AssertionFailure,
    StepExecutionFailure,
} from '../src/interpreter.js';

test('failure classification preserves the failed playwright step', () => {
    const assertion = classifyFailure(
        new AssertionFailure('Expected heading', 3),
        'Expected heading',
        120,
    );
    const locator = classifyFailure(
        new StepExecutionFailure(
            'Step 2 timed out',
            2,
            'click',
            new Error('timeout'),
        ),
        'Step 2 timed out',
        200,
    );

    assert.equal(assertion.failure_code, 'assertion_failed');
    assert.equal(assertion.failed_step, 3);
    assert.equal(locator.failure_code, 'locator_not_found');
    assert.equal(locator.failed_step, 2);
});

test('aria diagnostics remove visible input values', () => {
    const snapshot = [
        '- textbox "Username": standard_user',
        '- combobox "Country": Indonesia',
        '- button "Sign in"',
    ].join('\n');

    const sanitized = sanitizeAriaSnapshot(snapshot);

    assert.equal(sanitized.includes('standard_user'), false);
    assert.equal(sanitized.includes('Indonesia'), false);
    assert.equal(sanitized.includes('Sign in'), true);
});
