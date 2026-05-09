<?php

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => [
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'project_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('projects', 'id')->where('company_id', $companyId),
            ],
            'due_date' => ['required', 'date'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', 'in:private,visible'],
            'checklist' => ['nullable', 'array'],
        ];
    }
}
