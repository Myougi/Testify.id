<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Services\ProjectAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Issue::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $projectPlanIds = TestPlan::query()
            ->where('project_id', $this->activeProjectId())
            ->pluck('id');

        return [
            'test_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('test_plans', 'id')->where(
                    'project_id',
                    $this->activeProjectId(),
                ),
            ],
            'test_run_id' => [
                'nullable',
                'integer',
                Rule::exists('test_runs', 'id')->whereIn('test_plan_id', $projectPlanIds),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'expected_result' => ['nullable', 'string'],
            'actual_result' => ['nullable', 'string'],
            'priority' => ['required', 'string', Rule::in(Issue::priorities())],
            'severity' => ['required', 'string', Rule::in(Issue::severities())],
            'status' => ['required', 'string', Rule::in(Issue::statuses())],
            'reporter' => ['required', 'string', 'max:255'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', Rule::in(Issue::environments())],
            'defect_type' => ['nullable', 'string', Rule::in(Issue::defectTypes())],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $activeProject = $this->activeProject();

                if (! $activeProject) {
                    $validator->errors()->add(
                        'project',
                        'Select an active project before managing issues.',
                    );

                    return;
                }

                $this->validateLinkedArtifacts($validator, $activeProject->project_id);
            },
        ];
    }

    public function activeProject(): ?Project
    {
        $activeProjectId = $this->activeProjectId();

        if (! $this->user() || ! $activeProjectId) {
            return null;
        }

        return app(ProjectAccessService::class)
            ->accessibleProjectsQuery($this->user())
            ->whereKey($activeProjectId)
            ->first();
    }

    private function validateLinkedArtifacts(Validator $validator, int $projectId): void
    {
        $testPlanId = $this->integer('test_plan_id');
        $testRunId = $this->integer('test_run_id');

        if ($testRunId === 0) {
            return;
        }

        $testRun = TestRun::query()
            ->with('testPlan:id,project_id')
            ->find($testRunId);

        if (! $testRun || $testRun->testPlan?->project_id !== $projectId) {
            $validator->errors()->add(
                'test_run_id',
                'The selected test run does not belong to the active project.',
            );

            return;
        }

        if ($testPlanId > 0 && $testRun->test_plan_id !== $testPlanId) {
            $validator->errors()->add(
                'test_run_id',
                'The selected test run does not belong to the chosen test plan.',
            );
        }
    }

    private function activeProjectId(): ?int
    {
        $activeProjectId = $this->session()->get('qa_workspace_project_id');

        return app(ProjectAccessService::class)
            ->resolveAccessibleProject($this->user(), $activeProjectId)
            ?->project_id;
    }
}
