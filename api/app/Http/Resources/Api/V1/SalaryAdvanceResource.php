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
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_at' => ($this->requested_at ?? $this->created_at)?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'decision_comment' => $this->decision_comment,
            'repayment_months' => $this->repayment_months,
            'monthly_deduction' => $this->monthly_deduction,
            'amount_remaining' => $this->amount_remaining,
            'repayment_plan' => $this->repayment_plan ?? [],
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
                'email' => $this->employee->email,
                'company_id' => $this->employee->company_id,
            ]),
            'employee_name' => $this->whenLoaded('employee', fn () => trim(($this->employee->first_name ?? '').' '.($this->employee->last_name ?? ''))),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
