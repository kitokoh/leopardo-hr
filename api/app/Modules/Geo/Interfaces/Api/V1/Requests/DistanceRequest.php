<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Calcul de distance entre deux points (GEO-05, issue #8354, BC-33 GEO).
 *
 * Contrat `POST /v1/geo/distance` : `{from:{lat,lng}, to:{lat,lng}}` →
 * `{data:{distance_m}}`. Validation stricte des bornes WGS 84 — les
 * coordonnées invalides sont refusées ici (422) avant d'atteindre le
 * domaine (GeoPoint lève sinon InvalidGeoPointException, fail-closed).
 */
final class DistanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'array'],
            'from.lat' => ['required', 'numeric', 'between:-90,90'],
            'from.lng' => ['required', 'numeric', 'between:-180,180'],
            'to' => ['required', 'array'],
            'to.lat' => ['required', 'numeric', 'between:-90,90'],
            'to.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
