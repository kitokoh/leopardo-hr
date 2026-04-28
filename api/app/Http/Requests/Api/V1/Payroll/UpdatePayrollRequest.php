<?php

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['gross_salary' => ['sometimes', 'numeric', 'min:0'], 'overtime_amount' => ['sometimes', 'numeric', 'min:0'], 'bonuses' => ['sometimes', 'array'], 'bonuses.*.label' => ['required_with:bonuses', 'string', 'max:100'], 'bonuses.*.amount' => ['required_with:bonuses', 'numeric', 'min:0'], 'deductions' => ['sometimes', 'array'], 'deductions.*.label' => ['required_with:deductions', 'string', 'max:100'], 'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'], 'cotisations' => ['sometimes', 'array'], 'ir_amount' => ['sometimes', 'numeric', 'min:0'], 'advance_deduction' => ['sometimes', 'numeric', 'min:0'], 'absence_deduction' => ['sometimes', 'numeric', 'min:0'], 'penalty_deduction' => ['sometimes', 'numeric', 'min:0']];
    }
}
