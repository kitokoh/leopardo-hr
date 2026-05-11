<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\LeavePolicy;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'absence_type_id' => 'required|integer|exists:absence_types,id',
            'name' => 'required|string|max:200',
            'accrual_type' => 'required|in:monthly,yearly,manual',
            'accrual_amount' => 'required|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'carry_forward' => 'nullable|boolean',
            'max_carry_forward' => 'nullable|numeric|min:0',
            'carry_forward_expiry_days' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ];
    }
}
