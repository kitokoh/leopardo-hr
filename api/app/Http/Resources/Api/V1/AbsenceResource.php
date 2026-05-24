<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Absence */
class AbsenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'absence_type_id' => $this->absence_type_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_count' => $this->days_count,
            'status' => $this->status,
            'reason' => $this->reason,
            'proof_path' => $this->proof_path,
            'approved_by' => $this->approved_by,
            'rejected_reason' => $this->rejected_reason,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
            ]),
            'employee_name' => $this->whenLoaded('employee', fn () => trim(($this->employee->first_name ?? '').' '.($this->employee->last_name ?? ''))),
            'absence_type' => $this->whenLoaded('absenceType', fn () => [
                'id' => $this->absenceType->id,
                'name' => $this->absenceType->name,
                'code' => $this->absenceType->code,
                'deducts_leave' => $this->absenceType->deducts_leave,
            ]),
            'type' => $this->whenLoaded('absenceType', fn () => $this->absenceType->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
