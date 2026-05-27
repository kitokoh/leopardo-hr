<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\LeavePolicy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'accrual_type' => 'sometimes|in:monthly,yearly,manual',
            'accrual_amount' => 'sometimes|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'carry_forward' => 'boolean',
            'carry_forward_max' => 'nullable|numeric|min:0',
            'carry_forward_expiry_days' => 'nullable|integer|min:0',
            'requires_approval' => 'boolean',
            'approval_levels' => 'nullable|integer|min:1|max:5',
            'min_notice_days' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|integer|min:1',
            'applicable_roles' => 'nullable|array',
            'active' => 'boolean',
        ];
    }
}
