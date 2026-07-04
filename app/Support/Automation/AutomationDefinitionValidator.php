<?php

namespace App\Support\Automation;

use Illuminate\Validation\ValidationException;

class AutomationDefinitionValidator
{
    /** @var list<string> */
    private const ACTIONS = [
        'navigate', 'click', 'fill', 'select', 'check', 'uncheck', 'press', 'wait',
        'assert_visible', 'assert_text', 'assert_url', 'assert_value',
    ];

    /** @var list<string> */
    private const LOCATOR_ACTIONS = [
        'click', 'fill', 'select', 'check', 'uncheck', 'press',
        'assert_visible', 'assert_text', 'assert_value',
    ];

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, list<string>>  $credentialProfiles
     * @return array{version: int, steps: list<array<string, mixed>>}
     */
    public function validate(array $definition, array $credentialProfiles = []): array
    {
        $errors = [];
        $unknownRootKeys = array_diff(array_keys($definition), ['version', 'steps']);

        if ($unknownRootKeys !== []) {
            $errors['definition'] = 'Unsupported definition fields: '.implode(', ', $unknownRootKeys).'.';
        }

        if (($definition['version'] ?? null) !== 1) {
            $errors['definition.version'] = 'The automation definition version must be 1.';
        }

        $steps = $definition['steps'] ?? null;

        if (! is_array($steps) || ! array_is_list($steps) || $steps === []) {
            $errors['definition.steps'] = 'The automation definition must contain an ordered step list.';
        } elseif (count($steps) > 100) {
            $errors['definition.steps'] = 'The automation definition may not contain more than 100 steps.';
        } else {
            foreach ($steps as $index => $step) {
                $this->validateStep($step, $index, $credentialProfiles, $errors);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var list<array<string, mixed>> $steps */
        return ['version' => 1, 'steps' => $steps];
    }

    /**
     * @param  array<string, list<string>>  $credentialProfiles
     * @param  array<string, string>  $errors
     */
    private function validateStep(mixed $step, int $index, array $credentialProfiles, array &$errors): void
    {
        $pointer = 'definition.steps.'.$index;

        if (! is_array($step) || array_is_list($step)) {
            $errors[$pointer] = 'Each automation step must be an object.';

            return;
        }

        $action = $step['action'] ?? null;

        if (! is_string($action) || ! in_array($action, self::ACTIONS, true)) {
            $errors[$pointer.'.action'] = 'The automation action is not supported.';

            return;
        }

        $allowedKeys = $this->allowedKeys($action);
        $unknownKeys = array_diff(array_keys($step), $allowedKeys);

        if ($unknownKeys !== []) {
            $errors[$pointer] = 'Unsupported fields for '.$action.': '.implode(', ', $unknownKeys).'.';
        }

        if (in_array($action, self::LOCATOR_ACTIONS, true)) {
            $this->validateLocator($step['locator'] ?? null, $pointer.'.locator', $errors);
        }

        match ($action) {
            'navigate', 'assert_url' => $this->validateRelativePath($step['path'] ?? null, $pointer.'.path', $errors),
            'fill', 'select' => $this->validateInputValue($step, $pointer, $credentialProfiles, $errors),
            'press' => $this->validateNonEmptyString($step['key'] ?? null, $pointer.'.key', $errors),
            'wait' => $this->validateWait($step['milliseconds'] ?? null, $pointer.'.milliseconds', $errors),
            'assert_text', 'assert_value' => $this->validateNonEmptyString($step['value'] ?? null, $pointer.'.value', $errors),
            default => null,
        };
    }

    /** @return list<string> */
    private function allowedKeys(string $action): array
    {
        return match ($action) {
            'navigate', 'assert_url' => ['action', 'path'],
            'fill', 'select' => ['action', 'locator', 'value', 'secret'],
            'press' => ['action', 'locator', 'key'],
            'wait' => ['action', 'milliseconds'],
            'assert_text', 'assert_value' => ['action', 'locator', 'value'],
            default => ['action', 'locator'],
        };
    }

    /** @param array<string, string> $errors */
    private function validateLocator(mixed $locator, string $pointer, array &$errors): void
    {
        if (! is_array($locator) || array_is_list($locator)) {
            $errors[$pointer] = 'A locator object is required.';

            return;
        }

        $by = $locator['by'] ?? null;

        if (! is_string($by) || ! in_array($by, ['role', 'label', 'test_id', 'text', 'css'], true)) {
            $errors[$pointer.'.by'] = 'The locator strategy is not supported.';

            return;
        }

        $allowedKeys = $by === 'role' ? ['by', 'role', 'name'] : ['by', 'value'];

        if (array_diff(array_keys($locator), $allowedKeys) !== []) {
            $errors[$pointer] = 'The locator contains unsupported fields.';
        }

        if ($by === 'role') {
            $this->validateNonEmptyString($locator['role'] ?? null, $pointer.'.role', $errors);

            if (array_key_exists('name', $locator)) {
                $this->validateNonEmptyString($locator['name'], $pointer.'.name', $errors);
            }

            return;
        }

        $this->validateNonEmptyString($locator['value'] ?? null, $pointer.'.value', $errors);
    }

    /** @param array<string, string> $errors */
    private function validateRelativePath(mixed $path, string $pointer, array &$errors): void
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            $errors[$pointer] = 'Navigation paths must be same-origin paths beginning with a single slash.';
        }
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, list<string>>  $credentialProfiles
     * @param  array<string, string>  $errors
     */
    private function validateInputValue(array $step, string $pointer, array $credentialProfiles, array &$errors): void
    {
        $hasValue = array_key_exists('value', $step);
        $hasSecret = array_key_exists('secret', $step);

        if ($hasValue === $hasSecret) {
            $errors[$pointer] = 'Provide exactly one literal value or credential secret reference.';

            return;
        }

        if ($hasValue) {
            $this->validateNonEmptyString($step['value'], $pointer.'.value', $errors);

            return;
        }

        $secret = $step['secret'];

        if (! is_array($secret) || array_is_list($secret)) {
            $errors[$pointer.'.secret'] = 'The credential secret reference must be an object.';

            return;
        }

        if (array_diff(array_keys($secret), ['profile', 'field']) !== []) {
            $errors[$pointer.'.secret'] = 'The credential secret reference contains unsupported fields.';
        }

        $profile = $secret['profile'] ?? null;
        $field = $secret['field'] ?? null;

        $this->validateNonEmptyString($profile, $pointer.'.secret.profile', $errors);
        $this->validateNonEmptyString($field, $pointer.'.secret.field', $errors);

        if (is_string($profile) && is_string($field)) {
            if (! array_key_exists($profile, $credentialProfiles)) {
                $errors[$pointer.'.secret.profile'] = 'The credential profile is not configured for this project.';
            } elseif (! in_array($field, $credentialProfiles[$profile], true)) {
                $errors[$pointer.'.secret.field'] = 'The credential field is not declared by the selected profile.';
            }
        }
    }

    /** @param array<string, string> $errors */
    private function validateWait(mixed $milliseconds, string $pointer, array &$errors): void
    {
        if (! is_int($milliseconds) || $milliseconds < 0 || $milliseconds > 10000) {
            $errors[$pointer] = 'Wait duration must be an integer between 0 and 10000 milliseconds.';
        }
    }

    /** @param array<string, string> $errors */
    private function validateNonEmptyString(mixed $value, string $pointer, array &$errors): void
    {
        if (! is_string($value) || trim($value) === '') {
            $errors[$pointer] = 'This value must be a non-empty string.';
        }
    }
}
