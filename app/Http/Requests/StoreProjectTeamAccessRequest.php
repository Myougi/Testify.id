<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTeamAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team instanceof Team
            ? ($this->user()?->can('update', $team) ?? false)
            : false;
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
                Rule::exists(Project::class, 'project_id')
                    ->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
