<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueAttachment>
 */
class IssueAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'disk' => 'local',
            'path' => 'issues/example.png',
            'original_name' => 'example.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ];
    }
}
