<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeavePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'absence_type_id' => $this->absence_type_id,
            'name' => $this->name,
            'accrual_type' => $this->accrual_type,
            'accrual_amount' => $this->accrual_amount,
            'max_balance' => $this->max_balance,
            'carry_forward' => $this->carry_forward,
            'max_carry_forward' => $this->max_carry_forward,
            'carry_forward_expiry_days' => $this->carry_forward_expiry_days,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
