<?php

namespace App\Http\Requests;

use App\Models\TestCase;
use App\Models\TestRepositoryModule;
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
                Rule::exists('projects', 'project_id')->where(
                    'user_id',
                    $this->user()?->id,
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
                $module = TestRepositoryModule::query()->find($this->integer('module_id'));

                if (! $module || $module->project_id !== $this->integer('project_id')) {
                    $validator->errors()->add('module_id', 'The selected folder does not belong to this project.');
                }
            },
        ];
    }
}
