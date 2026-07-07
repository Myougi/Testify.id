<?php

namespace App\Http\Requests;

use App\Models\TestPlan;
use App\Models\TestRun;
use App\Services\ProjectAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTestRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TestRun::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_plan_id' => [
                'required',
                'integer',
                Rule::exists('test_plans', 'id')->whereIn(
                    'project_id',
                    $this->userProjectIds(),
                ),
            ],
            'test_case_ids' => ['required', 'array', 'min:1'],
            'test_case_ids.*' => ['required', 'integer', 'distinct', 'exists:test_cases,id'],
            'name' => ['required', 'string', 'max:255'],
            'environment' => ['required', 'string', Rule::in(TestRun::environments())],
            'priority' => ['required', 'string', Rule::in(TestRun::priorities())],
            'description' => ['nullable', 'string'],
            'tester_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePlanCaseMembership($validator);
            },
        ];
    }

    private function validatePlanCaseMembership(Validator $validator): void
    {
        $activeProjectId = $this->activeProjectId();
        $testPlan = TestPlan::query()
            ->whereKey($this->integer('test_plan_id'))
            ->first();

        if (! $testPlan) {
            return;
        }

        if ($activeProjectId !== null && $testPlan->project_id !== $activeProjectId) {
            $validator->errors()->add('test_plan_id', 'Test runs must use the active project.');

            return;
        }

        $selectedCaseIds = array_values(array_unique(array_map(
            'intval',
            $this->input('test_case_ids', []),
        )));

        $validCaseCount = $testPlan->testCases()
            ->whereIn('test_cases.id', $selectedCaseIds)
            ->count();

        if ($validCaseCount !== count($selectedCaseIds)) {
            $validator->errors()->add(
                'test_case_ids',
                'One or more selected test cases are not assigned to the selected test plan.',
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function userProjectIds(): array
    {
        return $this->user()
            ?->accessibleProjects()
            ->pluck('project_id')
            ->map(fn (mixed $projectId): int => (int) $projectId)
            ->all() ?? [];
    }

    private function activeProjectId(): ?int
    {
        $activeProjectId = $this->session()->get('qa_workspace_project_id');

        return app(ProjectAccessService::class)
            ->resolveAccessibleProject($this->user(), $activeProjectId)
            ?->project_id;
    }
}
