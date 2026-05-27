<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_type' => 'sometimes|in:cdi,cdd,stage,freelance,interim',
            'reference' => 'nullable|string|max:50',
            'end_date' => 'nullable|date',
            'job_title' => 'nullable|string|max:150',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'base_salary' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'salary_frequency' => 'nullable|in:monthly,hourly,daily',
            'work_hours_per_week' => 'nullable|numeric|min:0|max:168',
            'probation_end_date' => 'nullable|date',
            'benefits' => 'nullable|array',
            'clauses' => 'nullable|array',
            'status' => 'sometimes|in:draft,active,suspended,terminated',
            'termination_reason' => 'nullable|string',
        ];
    }
}
