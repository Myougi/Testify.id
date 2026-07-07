<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class SeniorQualityAssuranceAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $artifactType,
        public array $context = [],
        public ?int $requestedCount = 1,
        public int $maximumTestCaseCount = 20,
    ) {}

    public function instructions(): Stringable|string
    {
        $artifactLabel = match ($this->artifactType) {
            'test_plan' => 'test plan draft',
            'test_cases' => 'test case drafts',
            'issues' => 'issue drafts',
            'report_summary' => 'QA report summary draft',
            default => 'QA draft',
        };

        $artifactRules = match ($this->artifactType) {
            'test_plan' => implode(PHP_EOL, [
                '- Produce one realistic and comprehensive QA test plan draft.',
                '- Include a clear description, scope, risk and mitigation, strategy, entry/exit criteria, and release readiness.',
                '- Write risk and mitigation in realistic terms, with concrete examples and evidence from the provided context and write it inside test plan description.',
                '- Keep status aligned to a draft workflow.',
                '- Use only allowed priority values from the provided context.',
            ]),
            'test_cases' => implode(PHP_EOL, [
                $this->requestedCount !== null
                    ? '- Produce exactly '.$this->requestedCount.' practical test case draft'.($this->requestedCount === 1 ? '' : 's').'.'
                    : '- Choose the smallest sufficient set of practical test case drafts, between 1 and '.$this->maximumTestCaseCount.'.',
                '- Every case must be actionable, specific, and mapped to a module_id from context.',
                '- Include ordered steps with clear expected results.',
                '- Use only allowed priority, behavior, and type values from context.',
                '- Always include preconditions if they are relevant to the test case.',
                '- Always include test data if it is relevant to the test case and write it in preconditions or steps.',
                '- Never skip negative cases or edge cases unless the prompt clearly supports it.',
            ]),
            'issues' => implode(PHP_EOL, [
                '- Produce '.($this->requestedCount ?? 1).' issue draft'.($this->requestedCount === 1 ? '' : 's').'.',
                '- Frame the description as reproducible steps and keep expected vs actual behavior explicit.',
                '- Use only allowed priority, severity, environment, and defect type values from context.',
                '- Prefer linking to the selected test plan or run only when the prompt and context support it.',
            ]),
            'report_summary' => implode(PHP_EOL, [
                '- Summarize the provided QA metrics into concise leadership ready analysis.',
                '- Rank concrete quality risks from highest to lowest impact and support them with evidence from the report snapshot.',
                '- Identify coverage gaps, issue patterns, and execution constraints that could delay project completion.',
                '- Put the highest value completion action first, followed by practical prioritized next actions.',
                '- Do not invent metrics that are not present in the provided report snapshot.',
            ]),
            default => '- Produce a useful QA draft.',
        };

        $context = json_encode(
            $this->context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        return implode(PHP_EOL.PHP_EOL, [
            'You are a Senior Quality Assurance Engineer and Senior Quality Assurance Analyst.',
            'You generate practical, review-ready QA drafts for the Testify application.',
            'Skeptical and thorough — assume every feature has negative cases and edge cases.',
            'Apply boundary value analysis and equivalence partitioning instinctively.',
            'Return structured data only. Do not include markdown fences, prose outside schema fields, or explanations about the schema.',
            'Be concise, concrete, risk-aware, and realistic for a software QA workflow.',
            $artifactRules,
            'If information is missing, make the safest reasonable assumption and add a short note in warnings.',
            'Use precise language — "click Submit button" not "submit the form"',
            'Project context and allowed values:',
            $context ?: '{}',
            'Generate a '.$artifactLabel.' that matches the schema exactly.',
        ]);
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'draft' => match ($this->artifactType) {
                'test_plan' => $this->testPlanDraftSchema($schema)->required(),
                'test_cases' => $this->testCaseDraftSetSchema($schema)->required(),
                'issues' => $this->issueDraftSetSchema($schema)->required(),
                'report_summary' => $this->reportSummarySchema($schema)->required(),
                default => $schema->object()->required(),
            },
            'warnings' => $schema
                ->array()
                ->items($schema->string()->max(255))
                ->default([])
                ->required(),
        ];
    }

    private function testPlanDraftSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'name' => $schema->string()->max(255)->required(),
            'description' => $schema->string()->nullable()->required(),
            'scope' => $schema->string()->nullable()->required(),
            'strategy' => $schema->string()->nullable()->required(),
            'criteria' => $schema->string()->nullable()->required(),
            'priority' => $schema->string()->enum(['Low', 'Medium', 'High', 'Urgent'])->required(),
            'start_date' => $schema->string()->nullable()->required(),
            'due_date' => $schema->string()->nullable()->required(),
            'release_version' => $schema->string()->nullable()->required(),
            'tester' => $schema->string()->nullable()->required(),
        ])->withoutAdditionalProperties();
    }

    private function testCaseDraftSetSchema(JsonSchema $schema): ObjectType
    {
        $minimumCount = $this->requestedCount ?? 1;
        $maximumCount = $this->requestedCount ?? $this->maximumTestCaseCount;

        return $schema->object([
            'cases' => $schema->array()->items(
                $schema->object([
                    'module_id' => $schema->integer()->nullable()->required(),
                    'title' => $schema->string()->max(255)->required(),
                    'priority' => $schema->string()->enum(['Low', 'Medium', 'High', 'Urgent'])->required(),
                    'automation_status' => $schema->string()->enum(['Manual', 'Automated'])->required(),
                    'test_behavior' => $schema->string()->enum(['Positive', 'Negative'])->required(),
                    'test_type' => $schema->string()->enum([
                        'Acceptance',
                        'Accessibility',
                        'Compatibility',
                        'Functional',
                        'Integration',
                        'Performance',
                        'Regression',
                        'UI/UX',
                        'Security',
                        'Sanity',
                        'Smoke',
                    ])->required(),
                    'preconditions' => $schema->string()->nullable()->required(),
                    'steps' => $schema->array()->items(
                        $schema->object([
                            'action' => $schema->string()->required(),
                            'expected_result' => $schema->string()->required(),
                        ])->withoutAdditionalProperties()
                    )->required(),
                ])->withoutAdditionalProperties()
            )->min($minimumCount)->max($maximumCount)->required(),
        ])->withoutAdditionalProperties();
    }

    private function issueDraftSetSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'issues' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->max(255)->required(),
                    'description' => $schema->string()->required(),
                    'expected_result' => $schema->string()->nullable()->required(),
                    'actual_result' => $schema->string()->nullable()->required(),
                    'priority' => $schema->string()->enum(['Low', 'Medium', 'High', 'Urgent'])->required(),
                    'severity' => $schema->string()->enum(['Low', 'Medium', 'High', 'Critical'])->required(),
                    'status' => $schema->string()->enum([
                        'Open',
                        'In Investigation',
                        'In Testing',
                        'Solved',
                        'Deferred',
                    ])->required(),
                    'reporter' => $schema->string()->max(255)->required(),
                    'assignee' => $schema->string()->nullable()->required(),
                    'environment' => $schema->string()->enum([
                        'Development',
                        'SIT',
                        'Staging',
                        'UAT',
                        'Production',
                    ])->nullable()->required(),
                    'defect_type' => $schema->string()->enum([
                        'UI/UX',
                        'Functional',
                        'Compatibility',
                        'Database',
                        'Integration',
                        'Performance',
                        'Security',
                        'Usability',
                    ])->nullable()->required(),
                    'test_plan_id' => $schema->integer()->nullable()->required(),
                    'test_run_id' => $schema->integer()->nullable()->required(),
                ])->withoutAdditionalProperties()
            )->min(1)->required(),
        ])->withoutAdditionalProperties();
    }

    private function reportSummarySchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'executive_summary' => $schema->string()->required(),
            'quality_risks' => $schema->array()->items($schema->string())->required(),
            'coverage_notes' => $schema->array()->items($schema->string())->required(),
            'issue_analysis' => $schema->array()->items($schema->string())->required(),
            'recommendations' => $schema->array()->items($schema->string())->required(),
            'next_actions' => $schema->array()->items($schema->string())->required(),
        ])->withoutAdditionalProperties();
    }
}
