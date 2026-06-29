<?php

namespace App\Http\Requests;

use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $testPlan = $this->route('test_plan');

        return $testPlan instanceof TestPlan
            && ($this->user()?->can('update', $testPlan) ?? false);
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
                Rule::exists('projects', 'project_id')->where(
                    'user_id',
                    $this->user()?->id,
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'strategy' => ['nullable', 'string'],
            'criteria' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(TestPlan::statuses())],
            'priority' => ['required', 'string', Rule::in(TestPlan::priorities())],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'release_version' => ['nullable', 'string', 'max:255'],
            'tester' => ['nullable', 'string', 'max:255'],
        ];
    }
}
