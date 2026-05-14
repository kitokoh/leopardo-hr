<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeLoan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeLoan
 */
class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'repayment_months' => $this->repayment_months,
            'monthly_deduction' => $this->monthly_deduction,
            'total_repaid' => $this->total_repaid,
            'remaining' => $this->remaining,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
