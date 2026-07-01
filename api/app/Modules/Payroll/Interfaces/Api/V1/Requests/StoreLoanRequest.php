<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:3',
            'reason' => 'nullable|string|max:1000',
            'repayment_months' => 'required|integer|min:1|max:60',
            'monthly_deduction' => 'nullable|numeric|min:0',
        ];
    }
}
