<?php

namespace App\Http\Requests\Api\V1\SalaryAdvance;

use Illuminate\Foundation\Http\FormRequest;

class SalaryAdvanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['employee_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:pending,approved,rejected,active,repaid'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
