<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBulkTestCasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TestCase::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'project_id')->where(
                    'user_id',
                    $this->user()?->id,
                ),
            ],
            'module_id' => ['required', 'integer', 'exists:test_repository_modules,id'],
            'test_plan_id' => ['required', 'integer', 'exists:test_plans,id'],
            'cases' => [
                'required',
                'array',
                'min:1',
                'max:'.max(1, (int) config('ai.generation.test_cases.maximum_count', 20)),
            ],
            'cases.*.title' => ['required', 'string', 'max:255'],
            'cases.*.priority' => ['required', 'string', Rule::in(TestCase::priorities())],
            'cases.*.automation_status' => ['required', 'string', Rule::in(TestCase::automationStatuses())],
            'cases.*.test_behavior' => ['required', 'string', Rule::in(TestCase::behaviors())],
            'cases.*.test_type' => ['required', 'string', Rule::in(TestCase::types())],
            'cases.*.preconditions' => ['nullable', 'string'],
            'cases.*.steps' => ['nullable', 'array', 'max:50'],
            'cases.*.steps.*.action' => ['required', 'string'],
            'cases.*.steps.*.expected_result' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $activeProject = $this->activeProject();

                if (! $activeProject instanceof Project) {
                    $validator->errors()->add(
                        'project',
                        'Select an active project before creating AI-generated test cases.',
                    );

                    return;
                }

                if ($activeProject->project_id !== $this->integer('project_id')) {
                    $validator->errors()->add(
                        'project_id',
                        'Bulk AI case creation must use the active project.',
                    );
                }

                $module = TestRepositoryModule::query()->find($this->integer('module_id'));

                if (! $module || $module->project_id !== $activeProject->project_id) {
                    $validator->errors()->add(
                        'module_id',
                        'The selected folder does not belong to the active project.',
                    );
                }

                $testPlan = TestPlan::query()->find($this->integer('test_plan_id'));

                if (! $testPlan || $testPlan->project_id !== $activeProject->project_id) {
                    $validator->errors()->add(
                        'test_plan_id',
                        'The selected test plan does not belong to the active project.',
                    );
                }
            },
        ];
    }

    private function activeProject(): ?Project
    {
        $activeProjectId = $this->session()->get('qa_workspace_project_id');

        if (! $this->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        return $this->user()
            ->projects()
            ->whereKey((int) $activeProjectId)
            ->first();
    }
}
