<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salary_structure_id' => 'nullable|integer|exists:salary_structures,id',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'type' => 'required|in:earning,deduction,employer_contribution',
            'calculation_type' => 'required|in:fixed,percentage_of_base,percentage_of_gross,formula',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula' => 'nullable|string|max:500',
            'is_taxable' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ];
    }
}
