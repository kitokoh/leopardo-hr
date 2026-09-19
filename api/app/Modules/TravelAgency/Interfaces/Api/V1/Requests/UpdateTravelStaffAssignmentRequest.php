<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelStaffRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #7638 (TRAVEL-STAFF) — mise à jour d'une affectation : seul le rôle
 * métier est mutable. Changer de scope (bureau/voyage) ou d'employé =
 * révoquer puis recréer, pour préserver l'historique des manifestes.
 */
class UpdateTravelStaffAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelStaffAssignmentPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(TravelStaffRole::class)],
        ];
    }
}
