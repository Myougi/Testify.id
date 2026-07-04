import assert from 'node:assert/strict';
import { test } from 'node:test';
import { redact } from '../src/redact.js';
import { validateDefinition } from '../src/validator.js';

const profiles = [
    {
        name: 'Standard user',
        key: 'standard-user',
        fields: ['email', 'password'],
    },
];

test('accepts every supported action and locator strategy', () => {
    const definition = validateDefinition(
        {
            version: 1,
            steps: [
                { action: 'navigate', path: '/login' },
                {
                    action: 'fill',
                    locator: { by: 'label', value: 'Email' },
                    secret: { profile: 'standard-user', field: 'email' },
                },
                {
                    action: 'fill',
                    locator: { by: 'test_id', value: 'password' },
                    value: 'literal',
                },
                {
                    action: 'click',
                    locator: { by: 'role', role: 'button', name: 'Login' },
                },
                {
                    action: 'select',
                    locator: { by: 'css', value: '#environment' },
                    value: 'SIT',
                },
                {
                    action: 'check',
                    locator: { by: 'text', value: 'Remember me' },
                },
                {
                    action: 'uncheck',
                    locator: { by: 'css', value: '#newsletter' },
                },
                {
                    action: 'press',
                    locator: { by: 'label', value: 'Search' },
                    key: 'Enter',
                },
                { action: 'wait', milliseconds: 50 },
                {
                    action: 'assert_visible',
                    locator: { by: 'text', value: 'Dashboard' },
                },
                {
                    action: 'assert_text',
                    locator: { by: 'css', value: 'h1' },
                    value: 'Dashboard',
                },
                { action: 'assert_url', path: '/dashboard' },
                {
                    action: 'assert_value',
                    locator: { by: 'label', value: 'Search' },
                    value: 'query',
                },
            ],
        },
        profiles,
    );

    assert.equal(definition.steps.length, 13);
});

test('rejects cross-origin navigation and unsupported actions', () => {
    assert.throws(() =>
        validateDefinition(
            {
                version: 1,
                steps: [{ action: 'navigate', path: 'https://example.com' }],
            },
            profiles,
        ),
    );
    assert.throws(() =>
        validateDefinition(
            { version: 1, steps: [{ action: 'evaluate', script: 'alert(1)' }] },
            profiles,
        ),
    );
});

test('rejects undeclared credential fields', () => {
    assert.throws(() =>
        validateDefinition(
            {
                version: 1,
                steps: [
                    {
                        action: 'fill',
                        locator: { by: 'label', value: 'Token' },
                        secret: { profile: 'standard-user', field: 'token' },
                    },
                ],
            },
            profiles,
        ),
    );
});

test('redacts every configured secret with longest values first', () => {
    assert.equal(
        redact('login secret-123 and secret', ['secret', 'secret-123']),
        'login [REDACTED] and [REDACTED]',
    );
});
