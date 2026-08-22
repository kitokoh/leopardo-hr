<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\HR\Domain\Models\CareerEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement de carrière (plans de carrière, issue #5259).
 *
 * Les relations imbriquées sont mappées explicitement (id + nom) : on ne
 * sérialise jamais les champs sensibles de l'employé (salary_base, email…).
 *
 * @mixin CareerEvent
 */
class CareerEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'type' => $this->type,
            'status' => $this->status,
            'from_position_id' => $this->from_position_id,
            'to_position_id' => $this->to_position_id,
            'from_department_id' => $this->from_department_id,
            'to_department_id' => $this->to_department_id,
            'from_salary' => $this->from_salary !== null ? (float) $this->from_salary : null,
            'to_salary' => $this->to_salary !== null ? (float) $this->to_salary : null,
            'effective_date' => $this->effective_date?->toDateString(),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn (): array => [
                'id' => $this->employee?->id,
                'first_name' => $this->employee?->first_name,
                'last_name' => $this->employee?->last_name,
            ]),
            'from_position' => $this->whenLoaded('fromPosition', fn (): ?array => $this->fromPosition === null ? null : [
                'id' => $this->fromPosition->id,
                'name' => $this->fromPosition->name,
            ]),
            'to_position' => $this->whenLoaded('toPosition', fn (): ?array => $this->toPosition === null ? null : [
                'id' => $this->toPosition->id,
                'name' => $this->toPosition->name,
            ]),
            'from_department' => $this->whenLoaded('fromDepartment', fn (): ?array => $this->fromDepartment === null ? null : [
                'id' => $this->fromDepartment->id,
                'name' => $this->fromDepartment->name,
            ]),
            'to_department' => $this->whenLoaded('toDepartment', fn (): ?array => $this->toDepartment === null ? null : [
                'id' => $this->toDepartment->id,
                'name' => $this->toDepartment->name,
            ]),
            'approver' => $this->whenLoaded('approver', fn (): ?array => $this->approver === null ? null : [
                'id' => $this->approver->id,
                'first_name' => $this->approver->first_name,
                'last_name' => $this->approver->last_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
