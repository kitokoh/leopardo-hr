<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #7909 — Validation d'affectation d'un employé à une succursale.
 *
 * `employee_id` doit appartenir au tenant courant : cette contrainte
 * d'appartenance est validée dans `RestaurantBranchStaffService::assign()`
 * (422 EMPLOYEE_OUTSIDE_TENANT, même pattern que `FuelShiftService::assign()`),
 * la succursale cible venant du chemin (`{restaurantBranch}`, 404 sûr
 * cross-tenant dans le contrôleur).
 */
class StoreRestaurantBranchStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantBranchStaffPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'min:1'],
            'role' => ['nullable', 'string', 'max:80'],
        ];
    }
}
