<?php

namespace Database\Factories;

use App\Models\TestCase;
use App\Models\TestCaseAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCaseAutomation>
 */
class TestCaseAutomationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_case_id' => TestCase::factory()->state(['automation_status' => TestCase::AUTOMATION_AUTOMATED]),
            'driver' => TestCaseAutomation::DRIVER_PLAYWRIGHT_DSL,
            'schema_version' => 1,
            'status' => TestCaseAutomation::STATUS_DRAFT,
            'definition' => [
                'version' => 1,
                'steps' => [
                    ['action' => 'navigate', 'path' => '/'],
                    [
                        'action' => 'assert_visible',
                        'locator' => ['by' => 'role', 'role' => 'heading', 'name' => 'Welcome'],
                    ],
                ],
            ],
        ];
    }
}
