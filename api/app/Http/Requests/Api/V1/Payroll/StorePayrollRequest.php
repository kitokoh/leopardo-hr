<?php

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', 'min:1'], 'period_month' => ['required', 'integer', 'between:1,12'], 'period_year' => ['required', 'integer', 'min:2000'], 'gross_salary' => ['required', 'numeric', 'min:0'], 'overtime_amount' => ['nullable', 'numeric', 'min:0'], 'bonuses' => ['nullable', 'array'], 'bonuses.*.label' => ['required_with:bonuses', 'string', 'max:100'], 'bonuses.*.amount' => ['required_with:bonuses', 'numeric', 'min:0'], 'deductions' => ['nullable', 'array'], 'deductions.*.label' => ['required_with:deductions', 'string', 'max:100'], 'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'], 'cotisations' => ['nullable', 'array'], 'ir_amount' => ['nullable', 'numeric', 'min:0'], 'advance_deduction' => ['nullable', 'numeric', 'min:0'], 'absence_deduction' => ['nullable', 'numeric', 'min:0'], 'penalty_deduction' => ['nullable', 'numeric', 'min:0']];
    }
}
