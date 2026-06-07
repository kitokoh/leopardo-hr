<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payroll */
class PayrollResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'month' => $this->month,
            'year' => $this->year,
            'base_salary' => $this->base_salary,
            'gross_salary' => $this->gross_salary,
            'net_salary' => $this->net_salary,
            'currency' => currentCompany()?->currency ?? 'DZD',
            'total_deductions' => $this->total_deductions,
            'total_additions' => $this->total_additions,
            'status' => $this->status,
            'validated_at' => $this->validated_at,
            'validated_by' => $this->validated_by,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
                'matricule' => $this->employee->matricule,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
