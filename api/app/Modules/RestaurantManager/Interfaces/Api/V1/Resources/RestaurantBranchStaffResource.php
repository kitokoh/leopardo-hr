<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranchStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #7909 — Représentation API d'une affectation staff ↔ succursale.
 *
 * Interne au module (PA2-ARCH-010). L'employé (nom) est embarqué quand la
 * relation est chargée (liste paginée du contrôleur) — RH reste propriétaire
 * des employés, seuls id/prénom/nom sont exposés ici.
 *
 * @mixin RestaurantBranchStaff
 */
class RestaurantBranchStaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
            ]),
            'role' => $this->role,
            'assigned_at' => $this->assigned_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
