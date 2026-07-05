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
        $stepNumber = $index + 1;

        if (! is_array($generated) || array_is_list($generated)) {
            throw ValidationException::withMessages([$pointer => 'The generated step must be an object.']);
        }

        $action = $this->requiredString($generated['action'] ?? null, $pointer.'.action', $stepNumber, fieldName: 'action');

        if (! in_array($action, self::ACTIONS, true)) {
            throw ValidationException::withMessages([
                $pointer.'.action' => "Step {$stepNumber} uses an unsupported action.",
            ]);
        }

        return match ($action) {
            'navigate', 'assert_url' => [
                'action' => $action,
                'path' => $this->requiredString($generated['path'] ?? null, $pointer.'.path', $stepNumber, $action, 'path'),
            ],
            'fill', 'select' => $this->compileInputStep($generated, $action, $pointer, $stepNumber),
            'press' => [
                'action' => $action,
                'locator' => $this->compileLocator($generated, $pointer, $stepNumber, $action),
                'key' => $this->requiredString($generated['key'] ?? null, $pointer.'.key', $stepNumber, $action, 'key'),
            ],
            'wait' => [
                'action' => $action,
                'milliseconds' => $this->milliseconds($generated['milliseconds'] ?? null, $pointer.'.milliseconds', $stepNumber),
            ],
            'assert_text', 'assert_value' => [
                'action' => $action,
                'locator' => $this->compileLocator($generated, $pointer, $stepNumber, $action),
                'value' => $this->requiredString(
                    $generated['value'] ?? $generated['literal_value'] ?? null,
                    $pointer.'.value',
                    $stepNumber,
                    $action,
                    'value',
                ),
            ],
            default => [
                'action' => $action,
                'locator' => $this->compileLocator($generated, $pointer, $stepNumber, $action),
            ],
        };
    }

    /** @param array<string, mixed> $generated
     * @return array<string, mixed>
     */
    private function compileInputStep(array $generated, string $action, string $pointer, int $stepNumber): array
    {
        $literal = $this->nullableString($generated['value'] ?? $generated['literal_value'] ?? null);
        $secret = $this->secretReference($generated);
        $profile = $secret['profile'] ?? null;
        $field = $secret['field'] ?? null;
        $hasLiteral = $literal !== null;
        $hasSecret = $profile !== null || $field !== null;

        if ($hasLiteral === $hasSecret || ($hasSecret && ($profile === null || $field === null))) {
            throw ValidationException::withMessages([
                $pointer => "Step {$stepNumber} {$action} needs exactly one literal value or a complete credential reference.",
            ]);
        }

        return [
            'action' => $action,
            'locator' => $this->compileLocator($generated, $pointer, $stepNumber, $action),
            ...($hasLiteral
                ? ['value' => $literal]
                : ['secret' => ['profile' => $profile, 'field' => $field]]),
        ];
    }

    /** @param array<string, mixed> $generated
     * @return array<string, string>
     */
    private function compileLocator(array $generated, string $pointer, int $stepNumber, string $action): array
    {
        $locator = $generated['locator'] ?? null;

        if (is_array($locator) && ! array_is_list($locator)) {
            return $this->compileExistingLocator($locator, $pointer, $stepNumber, $action);
        }

        $by = $this->requiredString($generated['locator_by'] ?? null, $pointer.'.locator_by', $stepNumber, $action, 'locator_by');

        if (! in_array($by, self::LOCATORS, true)) {
            throw ValidationException::withMessages([
                $pointer.'.locator_by' => "Step {$stepNumber} {$action} uses an unsupported locator_by strategy.",
            ]);
        }

        if ($by === 'role') {
            $locator = [
                'by' => 'role',
                'role' => $this->requiredString($generated['locator_role'] ?? null, $pointer.'.locator_role', $stepNumber, $action, 'locator_role'),
            ];
            $name = $this->nullableString($generated['locator_name'] ?? null);

            return $name === null ? $locator : [...$locator, 'name' => $name];
        }

        return [
            'by' => $by,
            'value' => $this->requiredString($generated['locator_value'] ?? null, $pointer.'.locator_value', $stepNumber, $action, 'locator_value'),
        ];
    }

    /** @param array<string, mixed> $locator
     * @return array<string, string>
     */
    private function compileExistingLocator(array $locator, string $pointer, int $stepNumber, string $action): array
    {
        $by = $this->requiredString($locator['by'] ?? null, $pointer.'.by', $stepNumber, $action, 'locator.by');

        if (! in_array($by, self::LOCATORS, true)) {
            throw ValidationException::withMessages([
                $pointer.'.by' => "Step {$stepNumber} {$action} uses an unsupported locator strategy.",
            ]);
        }

        if ($by === 'role') {
            $compiled = [
                'by' => 'role',
                'role' => $this->requiredString($locator['role'] ?? null, $pointer.'.role', $stepNumber, $action, 'locator.role'),
            ];
            $name = $this->nullableString($locator['name'] ?? null);

            return $name === null ? $compiled : [...$compiled, 'name' => $name];
        }

        return [
            'by' => $by,
            'value' => $this->requiredString($locator['value'] ?? null, $pointer.'.value', $stepNumber, $action, 'locator.value'),
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

    private function milliseconds(mixed $value, string $pointer, int $stepNumber): int
    {
        if (is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0 || $value > 10000) {
            throw ValidationException::withMessages([
                $pointer => "Step {$stepNumber} wait milliseconds must be between 0 and 10000.",
            ]);
        }

        return $value;
    }

    private function requiredString(
        mixed $value,
        string $pointer,
        int $stepNumber,
        ?string $action = null,
        ?string $fieldName = null,
    ): string {
        $value = $this->nullableString($value);

        if ($value === null) {
            $field = $fieldName ?? $this->fieldName($pointer);
            $actionPrefix = $action === null ? '' : " {$action}";

            throw ValidationException::withMessages([
                $pointer => "Step {$stepNumber}{$actionPrefix} is missing {$field}.",
            ]);
        }

        return $value;
    }

    private function fieldName(string $pointer): string
    {
        $field = str($pointer)->after('definition.steps.')->after('.')->value();

        return match ($field) {
            'by' => 'locator.by',
            'role' => 'locator.role',
            'value' => 'locator.value',
            default => $field,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
