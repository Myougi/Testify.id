<?php

namespace Database\Factories;

use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationArtifact>
 */
class AutomationArtifactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_execution_id' => AutomationExecution::factory(),
            'type' => AutomationArtifact::TYPE_SCREENSHOT,
            'disk' => 's3',
            'path' => 'automation/example/screenshot.png',
            'original_name' => 'screenshot.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'sha256' => hash('sha256', 'screenshot'),
            'uploaded_at' => now(),
        ];
    }
}
