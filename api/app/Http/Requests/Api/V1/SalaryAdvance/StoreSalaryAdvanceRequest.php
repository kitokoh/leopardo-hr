<?php

namespace App\Http\Requests\Api\V1\SalaryAdvance;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryAdvanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['amount' => ['required', 'numeric', 'min:1'], 'reason' => ['nullable', 'string', 'max:1000'], 'repayment_months' => ['nullable', 'integer', 'min:1', 'max:24']];
    }
}
