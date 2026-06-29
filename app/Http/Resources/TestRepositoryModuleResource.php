<?php

namespace App\Http\Resources;

use App\Models\TestRepositoryModule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestRepositoryModule
 */
class TestRepositoryModuleResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'sort_order' => $this->sort_order,
            'children_count' => $this->whenCounted('children'),
            'test_cases_count' => $this->whenCounted('testCases'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
