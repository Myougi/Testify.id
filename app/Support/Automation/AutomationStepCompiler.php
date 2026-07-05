<?php

namespace App\Support\Automation;

use Illuminate\Validation\ValidationException;

class AutomationStepCompiler
{
    /** @var list<string> */
    private const ACTIONS = [
        'navigate', 'click', 'fill', 'select', 'check', 'uncheck', 'press', 'wait',
        'assert_visible', 'assert_text', 'assert_url', 'assert_value',
    ];

    /** @var list<string> */
    private const LOCATORS = ['role', 'label', 'test_id', 'text', 'css'];

    /**
     * @param  array<string, mixed>  $generated
     * @return array{version: int, steps: list<array<string, mixed>>}
     */
    public function compileDefinition(array $generated): array
    {
        $steps = $generated['steps'] ?? null;

        if (($generated['version'] ?? null) !== 1 || ! is_array($steps) || ! array_is_list($steps) || $steps === []) {
            throw ValidationException::withMessages([
                'definition' => 'The generated definition must use version 1 and contain an ordered step list.',
            ]);
        }

        $compiled = [];
        $errors = [];

        foreach ($steps as $index => $step) {
            try {
                $compiled[] = $this->compileStep($step, $index);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $errors[$field] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['version' => 1, 'steps' => $compiled];
    }

    /** @return array<string, mixed> */
    public function compileStep(mixed $generated, int $index = 0): array
    {
        $pointer = 'definition.steps.'.$index;

        if (! is_array($generated) || array_is_list($generated)) {
            throw ValidationException::withMessages([$pointer => 'The generated step must be an object.']);
        }

        $action = $this->requiredString($generated['action'] ?? null, $pointer.'.action');

        if (! in_array($action, self::ACTIONS, true)) {
            throw ValidationException::withMessages([$pointer.'.action' => 'The generated action is not supported.']);
        }

        return match ($action) {
            'navigate', 'assert_url' => [
                'action' => $action,
                'path' => $this->requiredString($generated['path'] ?? null, $pointer.'.path'),
            ],
            'fill', 'select' => $this->compileInputStep($generated, $action, $pointer),
            'press' => [
                'action' => $action,
                'locator' => $this->compileLocator($generated, $pointer),
                'key' => $this->requiredString($generated['key'] ?? null, $pointer.'.key'),
            ],
            'wait' => [
                'action' => $action,
                'milliseconds' => $this->milliseconds($generated['milliseconds'] ?? null, $pointer.'.milliseconds'),
            ],
            'assert_text', 'assert_value' => [
                'action' => $action,
                'locator' => $this->compileLocator($generated, $pointer),
                'value' => $this->requiredString($generated['value'] ?? $generated['literal_value'] ?? null, $pointer.'.value'),
            ],
            default => [
                'action' => $action,
                'locator' => $this->compileLocator($generated, $pointer),
            ],
        };
    }

    /** @param array<string, mixed> $generated
     * @return array<string, mixed>
     */
    private function compileInputStep(array $generated, string $action, string $pointer): array
    {
        $literal = $this->nullableString($generated['value'] ?? $generated['literal_value'] ?? null);
        $secret = $this->secretReference($generated);
        $profile = $secret['profile'] ?? null;
        $field = $secret['field'] ?? null;
        $hasLiteral = $literal !== null;
        $hasSecret = $profile !== null || $field !== null;

        if ($hasLiteral === $hasSecret || ($hasSecret && ($profile === null || $field === null))) {
            throw ValidationException::withMessages([
                $pointer => 'Generated fill and select steps need exactly one literal value or complete credential reference.',
            ]);
        }

        return [
            'action' => $action,
            'locator' => $this->compileLocator($generated, $pointer),
            ...($hasLiteral
                ? ['value' => $literal]
                : ['secret' => ['profile' => $profile, 'field' => $field]]),
        ];
    }

    /** @param array<string, mixed> $generated
     * @return array<string, string>
     */
    private function compileLocator(array $generated, string $pointer): array
    {
        $locator = $generated['locator'] ?? null;

        if (is_array($locator) && ! array_is_list($locator)) {
            return $this->compileExistingLocator($locator, $pointer);
        }

        $by = $this->requiredString($generated['locator_by'] ?? null, $pointer.'.locator_by');

        if (! in_array($by, self::LOCATORS, true)) {
            throw ValidationException::withMessages([$pointer.'.locator_by' => 'The generated locator strategy is not supported.']);
        }

        if ($by === 'role') {
            $locator = [
                'by' => 'role',
                'role' => $this->requiredString($generated['locator_role'] ?? null, $pointer.'.locator_role'),
            ];
            $name = $this->nullableString($generated['locator_name'] ?? null);

            return $name === null ? $locator : [...$locator, 'name' => $name];
        }

        return [
            'by' => $by,
            'value' => $this->requiredString($generated['locator_value'] ?? null, $pointer.'.locator_value'),
        ];
    }

    /** @param array<string, mixed> $locator
     * @return array<string, string>
     */
    private function compileExistingLocator(array $locator, string $pointer): array
    {
        $by = $this->requiredString($locator['by'] ?? null, $pointer.'.by');

        if (! in_array($by, self::LOCATORS, true)) {
            throw ValidationException::withMessages([$pointer.'.by' => 'The generated locator strategy is not supported.']);
        }

        if ($by === 'role') {
            $compiled = [
                'by' => 'role',
                'role' => $this->requiredString($locator['role'] ?? null, $pointer.'.role'),
            ];
            $name = $this->nullableString($locator['name'] ?? null);

            return $name === null ? $compiled : [...$compiled, 'name' => $name];
        }

        return [
            'by' => $by,
            'value' => $this->requiredString($locator['value'] ?? null, $pointer.'.value'),
        ];
    }

    /**
     * @param  array<string, mixed>  $generated
     * @return array{profile: string|null, field: string|null}
     */
    private function secretReference(array $generated): array
    {
        $secret = $generated['secret'] ?? null;

        if (is_array($secret) && ! array_is_list($secret)) {
            return [
                'profile' => $this->nullableString($secret['profile'] ?? null),
                'field' => $this->nullableString($secret['field'] ?? null),
            ];
        }

        return [
            'profile' => $this->nullableString($generated['secret_profile'] ?? null),
            'field' => $this->nullableString($generated['secret_field'] ?? null),
        ];
    }

    private function milliseconds(mixed $value, string $pointer): int
    {
        if (is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0 || $value > 10000) {
            throw ValidationException::withMessages([$pointer => 'Generated waits must be between 0 and 10000 milliseconds.']);
        }

        return $value;
    }

    private function requiredString(mixed $value, string $pointer): string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            throw ValidationException::withMessages([$pointer => 'This generated Playwright field is required.']);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
