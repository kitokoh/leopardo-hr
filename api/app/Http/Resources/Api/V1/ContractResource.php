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
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'contract_type' => $this->contract_type,
            'type' => $this->contract_type,
            'reference' => $this->reference,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'job_title' => $this->job_title,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'base_salary' => $this->base_salary,
            'salary' => $this->base_salary,
            'currency' => $this->currency,
            'salary_frequency' => $this->salary_frequency,
            'status' => $this->status,
            'work_hours_per_week' => $this->work_hours_per_week,
            'probation_end_date' => $this->probation_end_date?->toDateString(),
            'benefits' => $this->benefits,
            'clauses' => $this->clauses,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_document_path' => $this->signed_document_path,
            'termination_reason' => $this->termination_reason,
            'terminated_at' => $this->terminated_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'employee' => $this->whenLoaded('employee'),
            'department' => $this->whenLoaded('department'),
            'position' => $this->whenLoaded('position'),
            'amendments' => $this->whenLoaded('amendments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
