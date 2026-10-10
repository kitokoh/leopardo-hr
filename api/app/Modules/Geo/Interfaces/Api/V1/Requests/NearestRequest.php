<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recherche « les plus proches » (GEO-05, issue #8354, BC-33 GEO).
 *
 * Contrat `GET /v1/geo/nearest?type=&lat=&lng=&radius_km=&limit=`. Le rayon
 * est borné par la configuration `geo.nearest` (défaut 10 km, max 50 km) :
 * aucune requête spatiale débridée (protection des tables volumineuses).
 */
final class NearestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxRadiusKm = (float) config('geo.nearest.max_radius_km', 50.0);

        return [
            'type' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:'.$maxRadiusKm],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
