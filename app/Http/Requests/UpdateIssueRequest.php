<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\TestPlan;
use App\Models\TestRun;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue
            && ($this->user()?->can('update', $issue) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $issue = $this->route('issue');
        $projectId = $issue instanceof Issue ? $issue->project_id : 0;
        $projectPlanIds = TestPlan::query()
            ->where('project_id', $projectId)
            ->pluck('id');

        return [
            'test_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('test_plans', 'id')->where(
                    'project_id',
                    $projectId,
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
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $issue = $this->route('issue');

                if (! $issue instanceof Issue) {
                    return;
                }

                $this->validateLinkedArtifacts($validator, $issue->project_id);
            },
        ];
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
                'The selected test run does not belong to this issue project.',
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
}
