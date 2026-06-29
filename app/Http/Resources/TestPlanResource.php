<?php

namespace App\Http\Resources;

use App\Models\TestPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestPlan
 */
class TestPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'scope' => $this->scope,
            'strategy' => $this->strategy,
            'criteria' => $this->criteria,
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'release_version' => $this->release_version,
            'tester' => $this->tester,
            'project' => [
                'id' => $this->project_id,
                'name' => $this->project?->project_name,
            ],
            'assigned_test_cases_count' => $this->whenCounted('testCases'),
            'test_cases' => $this->whenLoaded(
                'testCases',
                fn (): array => $this->testCases
                    ->map(fn ($testCase): array => [
                        'id' => $testCase->id,
                        'case_key' => $testCase->case_key,
                        'title' => $testCase->title,
                        'priority' => $testCase->priority,
                        'automation_status' => $testCase->automation_status,
                        'test_behavior' => $testCase->test_behavior,
                        'test_type' => $testCase->test_type,
                        'module' => [
                            'id' => $testCase->module_id,
                            'name' => $testCase->module?->name,
                        ],
                    ])
                    ->all(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
