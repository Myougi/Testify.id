import type {
    AutomationDefinition,
    AutomationStep,
    CredentialProfileMetadata,
    LocatorDefinition,
} from './types.js';

const actions = new Set([
    'navigate',
    'click',
    'fill',
    'select',
    'check',
    'uncheck',
    'press',
    'wait',
    'assert_visible',
    'assert_text',
    'assert_url',
    'assert_value',
]);
const locatorActions = new Set([
    'click',
    'fill',
    'select',
    'check',
    'uncheck',
    'press',
    'assert_visible',
    'assert_text',
    'assert_value',
]);

export class DefinitionValidationError extends Error {}

export function validateDefinition(
    input: unknown,
    credentialProfiles: CredentialProfileMetadata[],
): AutomationDefinition {
    if (
        !isRecord(input) ||
        Object.keys(input).some((key) => !['version', 'steps'].includes(key))
    ) {
        throw new DefinitionValidationError(
            'The definition contains unsupported root fields.',
        );
    }

    if (
        input.version !== 1 ||
        !Array.isArray(input.steps) ||
        input.steps.length === 0 ||
        input.steps.length > 100
    ) {
        throw new DefinitionValidationError(
            'The definition must use version 1 and contain 1 to 100 steps.',
        );
    }

    const profiles = new Map(
        credentialProfiles.map((profile) => [
            profile.key,
            new Set(profile.fields),
        ]),
    );
    input.steps.forEach((step, index) => validateStep(step, index, profiles));

    return input as unknown as AutomationDefinition;
}

function validateStep(
    step: unknown,
    index: number,
    profiles: Map<string, Set<string>>,
): void {
    if (
        !isRecord(step) ||
        typeof step.action !== 'string' ||
        !actions.has(step.action)
    ) {
        throw new DefinitionValidationError(
            `Step ${index + 1} has an unsupported action.`,
        );
    }

    const action = step.action as AutomationStep['action'];
    const allowed = allowedKeys(action);

    if (Object.keys(step).some((key) => !allowed.has(key))) {
        throw new DefinitionValidationError(
            `Step ${index + 1} contains unsupported fields.`,
        );
    }

    if (locatorActions.has(action)) {
        validateLocator(step.locator, index);
    }

    if (action === 'navigate' || action === 'assert_url') {
        if (
            typeof step.path !== 'string' ||
            !step.path.startsWith('/') ||
            step.path.startsWith('//')
        ) {
            throw new DefinitionValidationError(
                `Step ${index + 1} must use a same-origin path.`,
            );
        }
    }

    if (action === 'fill' || action === 'select') {
        validateInput(step, index, profiles);
    }

    if (action === 'press') {
        requireString(step.key, index, 'key');
    }

    if (
        action === 'wait' &&
        (!Number.isInteger(step.milliseconds) ||
            Number(step.milliseconds) < 0 ||
            Number(step.milliseconds) > 10_000)
    ) {
        throw new DefinitionValidationError(
            `Step ${index + 1} has an invalid wait duration.`,
        );
    }

    if (action === 'assert_text' || action === 'assert_value') {
        requireString(step.value, index, 'value');
    }
}

function allowedKeys(action: AutomationStep['action']): Set<string> {
    if (action === 'navigate' || action === 'assert_url') {
        return new Set(['action', 'path']);
    }

    if (action === 'fill' || action === 'select') {
        return new Set(['action', 'locator', 'value', 'secret']);
    }

    if (action === 'press') {
        return new Set(['action', 'locator', 'key']);
    }

    if (action === 'wait') {
        return new Set(['action', 'milliseconds']);
    }

    if (action === 'assert_text' || action === 'assert_value') {
        return new Set(['action', 'locator', 'value']);
    }

    return new Set(['action', 'locator']);
}

function validateLocator(
    locator: unknown,
    index: number,
): asserts locator is LocatorDefinition {
    if (!isRecord(locator) || typeof locator.by !== 'string') {
        throw new DefinitionValidationError(
            `Step ${index + 1} requires a locator.`,
        );
    }

    if (locator.by === 'role') {
        if (
            Object.keys(locator).some(
                (key) => !['by', 'role', 'name'].includes(key),
            )
        ) {
            throw new DefinitionValidationError(
                `Step ${index + 1} has an invalid role locator.`,
            );
        }

        requireString(locator.role, index, 'locator role');

        if (locator.name !== undefined) {
            requireString(locator.name, index, 'locator name');
        }

        return;
    }

    if (
        !['label', 'test_id', 'text', 'css'].includes(locator.by) ||
        Object.keys(locator).some((key) => !['by', 'value'].includes(key))
    ) {
        throw new DefinitionValidationError(
            `Step ${index + 1} has an unsupported locator.`,
        );
    }

    requireString(locator.value, index, 'locator value');
}

function validateInput(
    step: Record<string, unknown>,
    index: number,
    profiles: Map<string, Set<string>>,
): void {
    const hasValue = Object.hasOwn(step, 'value');
    const hasSecret = Object.hasOwn(step, 'secret');

    if (hasValue === hasSecret) {
        throw new DefinitionValidationError(
            `Step ${index + 1} must have exactly one value source.`,
        );
    }

    if (hasValue) {
        requireString(step.value, index, 'value');

        return;
    }

    if (
        !isRecord(step.secret) ||
        Object.keys(step.secret).some(
            (key) => !['profile', 'field'].includes(key),
        ) ||
        typeof step.secret.profile !== 'string' ||
        typeof step.secret.field !== 'string' ||
        !profiles.get(step.secret.profile)?.has(step.secret.field)
    ) {
        throw new DefinitionValidationError(
            `Step ${index + 1} references an unknown credential field.`,
        );
    }
}

function requireString(
    value: unknown,
    index: number,
    label: string,
): asserts value is string {
    if (typeof value !== 'string' || value.trim() === '') {
        throw new DefinitionValidationError(
            `Step ${index + 1} requires a non-empty ${label}.`,
        );
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
