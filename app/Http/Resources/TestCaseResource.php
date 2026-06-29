<?php

namespace App\Http\Resources;

use App\Models\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestCase
 */
class TestCaseResource extends JsonResource
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
            'project_id' => $this->project_id,
            'module_id' => $this->module_id,
            'case_key' => $this->case_key,
            'title' => $this->title,
            'priority' => $this->priority,
            'automation_status' => $this->automation_status,
            'test_behavior' => $this->test_behavior,
            'test_type' => $this->test_type,
            'preconditions' => $this->preconditions,
            'project' => [
                'id' => $this->project_id,
                'name' => $this->project?->project_name,
            ],
            'module' => [
                'id' => $this->module_id,
                'project_id' => $this->module?->project_id,
                'parent_id' => $this->module?->parent_id,
                'name' => $this->module?->name,
                'key_prefix' => $this->module?->key_prefix,
            ],
            'steps' => $this->whenLoaded('steps', fn (): array => $this->steps
                ->map(fn ($step): array => [
                    'id' => $step->id,
                    'position' => $step->position,
                    'action' => $step->action,
                    'expected_result' => $step->expected_result,
                ])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
