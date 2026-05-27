<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveAccrual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveAccrual
 */
class LeaveAccrualResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'leave_policy_id' => $this->leave_policy_id,
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'effective_date' => $this->effective_date?->toDateString(),
            'created_by' => $this->created_by,
            'employee' => $this->whenLoaded('employee'),
            'leave_policy' => $this->whenLoaded('leavePolicy'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
