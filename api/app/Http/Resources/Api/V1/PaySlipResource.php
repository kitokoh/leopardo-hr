<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PaySlip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaySlip
 */
class PaySlipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'gross_salary' => $this->gross_salary,
            'total_deductions' => $this->total_deductions,
            'net_salary' => $this->net_salary,
            'employer_contributions' => $this->employer_contributions,
            'total_cost' => $this->total_cost,
            'working_days' => $this->working_days,
            'actual_days_worked' => $this->actual_days_worked,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            'employee' => $this->whenLoaded('employee'),
            'lines' => $this->whenLoaded('lines'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
