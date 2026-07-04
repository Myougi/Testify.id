export function redact(value: string, secrets: string[]): string {
    return secrets
        .filter((secret) => secret.length > 0)
        .sort((left, right) => right.length - left.length)
        .reduce(
            (sanitized, secret) => sanitized.replaceAll(secret, '[REDACTED]'),
            value,
        );
}
