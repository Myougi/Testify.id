<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class AutomationHealingAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /** @param array<string, mixed> $context */
    public function __construct(public array $context) {}

    public function instructions(): Stringable|string
    {
        $context = json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return implode(PHP_EOL.PHP_EOL, [
            'You are a senior Playwright test healer. Diagnose one failed browser automation step from sanitized browser evidence.',
            'Return one complete replacement step that preserves the manual test intent while addressing the observed failure.',
            'You may change the failed action or assertion, but you must not change any other definition step.',
            'Use only: navigate, click, fill, select, check, uncheck, press, wait, assert_visible, assert_text, assert_url, assert_value.',
            'Prefer locators in this order: role, label, test_id, exact text, CSS. Never use XPath.',
            'Use only locator values supported by the supplied browser evidence. Do not invent hidden elements.',
            'Navigation and URL assertions must use same-origin paths beginning with one slash.',
            'Use only declared credential profile and field names. Never return a credential value.',
            'Do not emit JavaScript, shell commands, file uploads, browser storage access, comments, markdown, or unsupported fields.',
            'Rely on Playwright actionability and auto-waiting. Use wait only when evidence shows an explicit elapsed-time requirement.',
            'Explain why the replacement addresses the failure and report confidence from 0 to 100.',
            'Healing context:',
            $context ?: '{}',
        ]);
    }

    public function timeout(): int
    {
        return (int) config('automation.ai_healing.timeout_seconds', 180);
    }

    public function model(): string
    {
        return (string) (config('automation.ai_healing.model')
            ?? config('automation.ai.model')
            ?? config('ai.providers.openrouter.models.text.cheapest')
            ?? config('ai.providers.openrouter.models.text.default'));
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
            'replacement_step' => $schema->object([
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
            ])->withoutAdditionalProperties()->required(),
            'rationale' => $schema->string()->max(2000)->required(),
            'confidence' => $schema->integer()->min(0)->max(100)->required(),
        ];
    }
}
