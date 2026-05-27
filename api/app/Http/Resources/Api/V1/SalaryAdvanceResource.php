<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SalaryAdvance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalaryAdvance
 */
class SalaryAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'employee' => $this->whenLoaded('employee'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
