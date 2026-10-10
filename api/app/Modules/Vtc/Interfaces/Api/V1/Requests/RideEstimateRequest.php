<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Devis d'une course VTC (BC-34 VTC, VTC-03/#8359).
 *
 * Contrat `POST /v1/vtc/rides/estimate` : `{pickup:{lat,lng},
 * dropoff:{lat,lng}, fare_profile_id?}`. Validation stricte des bornes
 * WGS 84 (fail-closed avant le domaine GeoPoint).
 */
final class RideEstimateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pickup' => ['required', 'array'],
            'pickup.lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff' => ['required', 'array'],
            'dropoff.lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng' => ['required', 'numeric', 'between:-180,180'],
            'fare_profile_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
