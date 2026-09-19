<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelStaffAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #7638 (TRAVEL-STAFF) — représentation API d'une affectation d'équipage.
 *
 * Interne au module (PA2-ARCH-010). `employee_id` par valeur : pas de
 * sous-objet employé ici — le manifeste enrichit lui-même les noms.
 *
 * @mixin TravelStaffAssignment
 */
class TravelStaffAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'role' => $this->role,
            'office_id' => $this->office_id,
            'trip_id' => $this->trip_id,
            'status' => $this->status,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
