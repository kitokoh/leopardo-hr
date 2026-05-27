<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalance
 */
class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'absence_type_id' => $this->absence_type_id,
            'balance' => $this->balance,
            'used' => $this->used,
            'pending' => $this->pending,
            'year' => $this->year,
            'employee' => $this->whenLoaded('employee'),
            'absence_type' => $this->whenLoaded('absenceType'),
        ];
    }
}
