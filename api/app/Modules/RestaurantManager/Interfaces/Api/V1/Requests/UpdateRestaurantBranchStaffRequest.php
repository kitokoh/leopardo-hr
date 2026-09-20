<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #7909 — Validation de mise à jour du rôle d'une affectation staff.
 *
 * Seul le `role` est modifiable (changer d'employé ou de succursale =
 * retrait + nouvelle affectation). L'autorisation est tranchée par
 * `RestaurantBranchStaffPolicy::update()`.
 */
class UpdateRestaurantBranchStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantBranchStaffPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['present', 'nullable', 'string', 'max:80'],
        ];
    }
}
