<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Devis public marketplace VTC (#8423, M-12).
 *
 * Contrat `POST /public/vtc/marketplace/estimate` :
 * `{operator: slug, pickup:{lat,lng}, dropoff:{lat,lng}}`. L'opérateur est
 * identifié par son slug PUBLIC (jamais d'identifiant interne) ; la
 * résolution tenant est faite côté contrôleur (fail-closed 404 uniforme si
 * non opt-in). Validation stricte des bornes WGS 84 — mêmes règles que le
 * devis authentifié (`RideEstimateRequest`), fail-closed avant le domaine
 * `GeoPoint` (VO partagé App\Shared\Geo).
 */
final class MarketplaceEstimateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operator' => ['required', 'string', 'max:160'],
            'pickup' => ['required', 'array'],
            'pickup.lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff' => ['required', 'array'],
            'dropoff.lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
