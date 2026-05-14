<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'salary' => $this->salary,
            'currency' => $this->currency,
            'position' => $this->position,
            'status' => $this->status,
            'work_hours_per_week' => $this->work_hours_per_week,
            'probation_end_date' => $this->probation_end_date?->toDateString(),
            'employee' => $this->whenLoaded('employee'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
