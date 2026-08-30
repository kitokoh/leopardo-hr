<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-319 (#6049) — Validation de création d'une image de véhicule de
 * location. `position` optionnelle (défaut 0, contrainte DB d'unicité par
 * véhicule).
 */
class StoreTravelRentalVehicleImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRentalVehiclePolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
