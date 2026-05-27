<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\LeavePolicy;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccrualLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,id',
            'leave_policy_id' => 'required|integer|exists:leave_policies,id',
            'amount' => 'required|numeric',
            'type' => 'required|in:accrual,adjustment,carry_forward',
            'description' => 'nullable|string|max:255',
            'effective_date' => 'required|date',
        ];
    }
}
