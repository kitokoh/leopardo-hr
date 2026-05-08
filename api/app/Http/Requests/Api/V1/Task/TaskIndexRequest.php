<?php

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'project_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('projects', 'id')->where('company_id', $companyId),
            ],
            'status' => ['nullable', 'in:todo,inprogress,review,done,rejected,cancelled'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'assigned_to' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
