<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'code' => 'sometimes|string|max:50',
            'type' => 'sometimes|in:earning,deduction,employer_contribution',
            'calculation_type' => 'sometimes|in:fixed,percentage_of_base,percentage_of_gross,formula',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula' => 'nullable|string|max:500',
            'is_taxable' => 'sometimes|boolean',
            'is_recurring' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean',
        ];
    }
}
