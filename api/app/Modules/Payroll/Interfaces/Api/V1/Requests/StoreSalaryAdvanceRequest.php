<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'repayment_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            // PA2-MOB-006: optional supporting document (justification,
            // quote, invoice, etc.) attached at request time.
            'proof' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,heic'],
        ];
    }
}
