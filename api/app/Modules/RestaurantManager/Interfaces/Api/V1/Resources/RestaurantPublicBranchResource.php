<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-901 (#7746) — Item PUBLIC de l'annuaire des restaurants.
 *
 * DTO public STRICT (preset multitenancy) : AUCUNE donnée interne — ni
 * `company_id`, ni `id` brut, ni statut. Le contrat public est : slug
 * (identifiant public), nom, type, cuisines, ville, description, cover,
 * lat/lng et `distance_km` (uniquement en recherche par proximité `near=`).
 *
 * La ressource enveloppe une ligne SQL brute (stdClass — la requête annuaire
 * traverse les tenants du schéma partagé), d'où l'accès par
 * `get_object_vars()` plutôt que par propriétés (PHPStan level 8).
 */
class RestaurantPublicBranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = get_object_vars((object) $this->resource);

        $cuisines = $row['cuisine_types'] ?? null;

        if (is_string($cuisines)) {
            $decoded = json_decode($cuisines, true);
            $cuisines = is_array($decoded) ? array_values($decoded) : null;
        } elseif (! is_array($cuisines)) {
            $cuisines = null;
        }

        $data = [
            'slug' => $row['public_slug'] ?? null,
            'name' => $row['name'] ?? null,
            'establishment_type' => $row['establishment_type'] ?? null,
            'cuisine_types' => $cuisines,
            'city' => $row['city'] ?? null,
            'description' => $row['public_description'] ?? null,
            'cover_image_url' => $row['cover_image_url'] ?? null,
            'latitude' => isset($row['latitude']) && is_numeric($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) && is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
        ];

        if (isset($row['distance_km']) && is_numeric($row['distance_km'])) {
            $data['distance_km'] = round((float) $row['distance_km'], 2);
        }

        return $data;
    }
}
