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
            'Convert the supplied manual test case into the smallest safe Playwright DSL definition that preserves its intent.',
            'Use version 1 and only these actions: navigate, click, fill, select, check, uncheck, press, wait, assert_visible, assert_text, assert_url, assert_value.',
            'Navigation paths must begin with one slash and must never contain an origin.',
            'Prefer role, label, and test_id locators. Use text or css or xpath only when an accessible locator cannot express the step.',
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
        $locator = $schema->object([
            'by' => $schema->string()->enum(['role', 'label', 'test_id', 'text', 'css', 'xpath'])->required(),
            'role' => $schema->string()->nullable(),
            'name' => $schema->string()->nullable(),
            'value' => $schema->string()->nullable(),
        ])->withoutAdditionalProperties();

        $secret = $schema->object([
            'profile' => $schema->string()->required(),
            'field' => $schema->string()->required(),
        ])->withoutAdditionalProperties();

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
                        'locator' => $locator->nullable(),
                        'value' => $schema->string()->nullable(),
                        'secret' => $secret->nullable(),
                        'key' => $schema->string()->nullable(),
                        'milliseconds' => $schema->integer()->nullable(),
                    ])->withoutAdditionalProperties()
                )->min(1)->max(100)->required(),
            ])->withoutAdditionalProperties()->required(),
            'warnings' => $schema->array()->items($schema->string()->max(255))->default([])->required(),
        ];
    }
}
