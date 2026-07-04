<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class AutomationDefinitionAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /** @param array<string, mixed> $context */
    public function __construct(public array $context) {}

    public function instructions(): Stringable|string
    {
        $context = json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return implode(PHP_EOL.PHP_EOL, [
            'You are a senior browser automation engineer for Testify.',
            'Convert the supplied manual test case into the smallest reliable Playwright DSL definition that preserves its exact intent and ordered steps.',
            'Think like a Playwright test generator: map every manual action to a browser interaction and every expected result to a meaningful assertion.',
            'Use version 1 and only these actions: navigate, click, fill, select, check, uncheck, press, wait, assert_visible, assert_text, assert_url, assert_value.',
            'Navigation paths must begin with one slash and must never contain an origin.',
            'Prefer locator strategies in this order: role, label, test_id, exact text, then CSS. Never use XPath.',
            'Role locators need a valid ARIA role and should include an accessible name when known.',
            'Rely on Playwright locator actionability and auto-waiting. Emit a fixed wait only when the manual scenario explicitly requires elapsed time.',
            'Keep scenarios independent and assume a fresh browser context. Do not invent setup state that is absent from the test case.',
            'Use credential secret references for authentication data. Never place passwords, tokens, or API keys in literal values.',
            'Credential references contain profile and field. Use only profile keys and fields provided in context.',
            'Do not emit JavaScript, shell commands, file uploads, comments, markdown, or unsupported fields.',
            'Return warnings when a reliable selector or assertion cannot be inferred. The definition will require browser validation and user approval.',
            'Test case context:',
            $context ?: '{}',
        ]);
    }

    /** @return Message[] */
    public function messages(): iterable
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'definition' => $schema->object([
                'version' => $schema->integer()->enum([1])->required(),
                'steps' => $schema->array()->items(
                    $schema->object([
                        'action' => $schema->string()->enum([
                            'navigate', 'click', 'fill', 'select', 'check', 'uncheck', 'press', 'wait',
                            'assert_visible', 'assert_text', 'assert_url', 'assert_value',
                        ])->required(),
                        'path' => $schema->string()->nullable(),
                        'locator_by' => $schema->string()->enum(['role', 'label', 'test_id', 'text', 'css'])->nullable(),
                        'locator_role' => $schema->string()->nullable(),
                        'locator_name' => $schema->string()->nullable(),
                        'locator_value' => $schema->string()->nullable(),
                        'literal_value' => $schema->string()->nullable(),
                        'secret_profile' => $schema->string()->nullable(),
                        'secret_field' => $schema->string()->nullable(),
                        'key' => $schema->string()->nullable(),
                        'milliseconds' => $schema->integer()->nullable(),
                    ])->withoutAdditionalProperties()
                )->min(1)->max(100)->required(),
            ])->withoutAdditionalProperties()->required(),
            'warnings' => $schema->array()->items($schema->string()->max(255))->default([])->required(),
        ];
    }
}
