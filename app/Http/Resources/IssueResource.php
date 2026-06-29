<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Issue
 */
class IssueResource extends JsonResource
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
            'issue_key' => sprintf('ISS-%03d', $this->id),
            'title' => $this->title,
            'description' => $this->description,
            'expected_result' => $this->expected_result,
            'actual_result' => $this->actual_result,
            'priority' => $this->priority,
            'severity' => $this->severity,
            'status' => $this->status,
            'reporter' => $this->reporter,
            'assignee' => $this->assignee,
            'environment' => $this->environment,
            'defect_type' => $this->defect_type,
            'project' => [
                'id' => $this->project_id,
                'name' => $this->project?->project_name,
            ],
            'test_plan' => [
                'id' => $this->test_plan_id,
                'name' => $this->testPlan?->name,
            ],
            'test_run' => [
                'id' => $this->test_run_id,
                'name' => $this->testRun?->name,
                'status' => $this->testRun?->status,
                'test_plan' => [
                    'id' => $this->testRun?->test_plan_id,
                    'name' => $this->testRun?->testPlan?->name,
                ],
            ],
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
