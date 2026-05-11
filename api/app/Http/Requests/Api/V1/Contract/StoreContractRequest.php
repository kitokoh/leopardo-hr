<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,id',
            'type' => 'required|in:cdi,cdd,stage,freelance,interim',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'salary' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'position' => 'nullable|string|max:200',
            'department_id' => 'nullable|integer|exists:departments,id',
            'work_hours_per_week' => 'nullable|numeric|min:0|max:168',
            'probation_end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
