<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => $actor->isManager()
                ? [
                    'required',
                    'integer',
                    Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
                ]
                : 'prohibited',
            'loan_type' => 'required|in:personal,housing,vehicle,education,emergency',
            'amount' => 'required|numeric|min:1',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'installments' => 'required|integer|min:1|max:120',
            'start_date' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }
}
