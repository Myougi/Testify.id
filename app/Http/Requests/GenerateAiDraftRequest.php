<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\TestCase;
use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAiDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        return match ((string) $this->input('artifact_type')) {
            'test_plan' => $this->user()->can('create', TestPlan::class),
            'test_cases' => $this->user()->can('create', TestCase::class),
            'issues' => $this->user()->can('create', Issue::class),
            'report_summary' => true,
            default => false,
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artifact_type' => [
                'required',
                'string',
                Rule::in(['test_plan', 'test_cases', 'issues', 'report_summary']),
            ],
            'prompt' => ['required', 'string', 'max:4000'],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'project_id')->where('user_id', $this->user()?->id),
            ],
            'test_plan_id' => ['nullable', 'integer'],
            'test_run_id' => ['nullable', 'integer'],
            'module_id' => ['nullable', 'integer'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'report_filters' => ['nullable', 'array'],
            'report_filters.scope' => ['nullable', 'string', 'max:50'],
            'report_filters.range' => ['nullable', 'string', 'in:7d,30d,custom'],
            'report_filters.start_date' => ['nullable', 'date'],
            'report_filters.end_date' => ['nullable', 'date', 'after_or_equal:report_filters.start_date'],
            'report_filters.grouping' => ['nullable', 'string', 'in:daily,weekly'],
            'report_filters.sections' => ['nullable', 'array'],
            'report_filters.sections.*' => ['nullable', 'string', 'max:50'],
        ];
    }
}
