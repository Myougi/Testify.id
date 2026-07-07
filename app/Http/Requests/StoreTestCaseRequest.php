<?php

namespace App\Http\Requests;

use App\Models\TestCase;
use App\Models\TestRepositoryModule;
use App\Services\ProjectAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTestCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TestCase::class) ?? false;
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
            'module_id' => ['required', 'integer', 'exists:test_repository_modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'string', Rule::in(TestCase::priorities())],
            'automation_status' => ['required', 'string', Rule::in(TestCase::automationStatuses())],
            'test_behavior' => ['required', 'string', Rule::in(TestCase::behaviors())],
            'test_type' => ['required', 'string', Rule::in(TestCase::types())],
            'preconditions' => ['nullable', 'string'],
            'steps' => ['nullable', 'array', 'max:50'],
            'steps.*.action' => ['required', 'string'],
            'steps.*.expected_result' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $activeProjectId = $this->activeProjectId();

                if ($activeProjectId !== null && $this->integer('project_id') !== $activeProjectId) {
                    $validator->errors()->add('project_id', 'Test cases must use the active project.');

                    return;
                }

                $module = TestRepositoryModule::query()->find($this->integer('module_id'));

                if (! $module || $module->project_id !== $this->integer('project_id')) {
                    $validator->errors()->add('module_id', 'The selected folder does not belong to this project.');
                }
            },
        ];
    }

    private function activeProjectId(): ?int
    {
        $activeProjectId = $this->session()->get('qa_workspace_project_id');

        return app(ProjectAccessService::class)
            ->resolveAccessibleProject($this->user(), $activeProjectId)
            ?->project_id;
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
