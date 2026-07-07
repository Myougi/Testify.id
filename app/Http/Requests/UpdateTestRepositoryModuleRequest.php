<?php

namespace App\Http\Requests;

use App\Models\TestRepositoryModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTestRepositoryModuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $module = $this->route('module');

        return $module instanceof TestRepositoryModule
            && ($this->user()?->can('update', $module) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'project_id')->whereIn(
                    'project_id',
                    $this->accessibleProjectIds(),
                ),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:test_repository_modules,id'],
            'name' => ['required', 'string', 'max:255'],
            'key_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key_prefix' => strtoupper((string) $this->input('key_prefix')),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $module = $this->route('module');
                $parentId = $this->integer('parent_id');

                if (! $module instanceof TestRepositoryModule || $parentId === 0) {
                    return;
                }

                if ($parentId === $module->id) {
                    $validator->errors()->add('parent_id', 'A folder cannot be its own parent.');

                    return;
                }

                $parent = TestRepositoryModule::query()->find($parentId);

                if (! $parent || $parent->project_id !== $this->integer('project_id')) {
                    $validator->errors()->add('parent_id', 'The selected parent folder does not belong to this project.');

                    return;
                }

                $ancestor = $parent;

                while ($ancestor instanceof TestRepositoryModule) {
                    if ($ancestor->id === $module->id) {
                        $validator->errors()->add('parent_id', 'A folder cannot be moved beneath one of its child folders.');

                        return;
                    }

                    $ancestor = $ancestor->parent;
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    private function accessibleProjectIds(): array
    {
        return $this->user()
            ?->accessibleProjects()
            ->pluck('project_id')
            ->map(fn (mixed $projectId): int => (int) $projectId)
            ->all() ?? [];
    }
}
