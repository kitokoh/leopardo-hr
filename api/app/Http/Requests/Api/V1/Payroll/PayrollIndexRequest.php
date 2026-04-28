<?php

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['employee_id' => ['nullable', 'integer', 'min:1'], 'month' => ['nullable', 'integer', 'between:1,12'], 'year' => ['nullable', 'integer', 'min:2000'], 'status' => ['nullable', 'in:draft,validated'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
