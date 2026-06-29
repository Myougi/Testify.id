<?php

namespace App\Http\Requests;

use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignTestPlanCasesRequest extends FormRequest
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
            'test_case_ids' => ['required', 'array', 'min:1'],
            'test_case_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
