import type {
    CredentialProfileMetadata,
    CredentialValues,
    SecretReference,
} from './types.js';

export class CredentialConfigurationError extends Error {}

export function parseCredentialValues(
    raw: string | undefined,
): CredentialValues {
    if (!raw) {
        return {};
    }

    const value: unknown = JSON.parse(raw);

    if (!isRecord(value)) {
        throw new CredentialConfigurationError(
            'Credential profiles must be a JSON object.',
        );
    }

    const profiles: CredentialValues = {};

    for (const [key, fields] of Object.entries(value)) {
        if (!isRecord(fields)) {
            throw new CredentialConfigurationError(
                `Credential profile ${key} must be an object.`,
            );
        }

        profiles[key] = {};

        for (const [field, secret] of Object.entries(fields)) {
            if (typeof secret !== 'string' || secret === '') {
                throw new CredentialConfigurationError(
                    `Credential field ${key}.${field} must be a non-empty string.`,
                );
            }

            profiles[key][field] = secret;
        }
    }

    return profiles;
}

export function validateCredentialMetadata(
    metadata: CredentialProfileMetadata[],
    values: CredentialValues,
): void {
    for (const profile of metadata) {
        const supplied = values[profile.key];

        if (!supplied) {
            continue;
        }

        const undeclared = Object.keys(supplied).find(
            (field) => !profile.fields.includes(field),
        );

        if (undeclared) {
            throw new CredentialConfigurationError(
                `Credential field ${profile.key}.${undeclared} is not declared in Testify.`,
            );
        }
    }
}

export function resolveSecret(
    reference: SecretReference,
    values: CredentialValues,
): string {
    const value = values[reference.profile]?.[reference.field];

    if (!value) {
        throw new CredentialConfigurationError(
            `Credential ${reference.profile}.${reference.field} is unavailable.`,
        );
    }

    return value;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
