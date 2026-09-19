<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-901 (#7746) — Représentation PRIVÉE (gestion tenant) du profil
 * public d'une succursale.
 *
 * Interne au module (PA2-ARCH-010) : c'est la vue « gérant » — la vue
 * publique (annuaire) est portée par RestaurantPublicBranchResource, qui
 * n'expose ni `id` ni aucune donnée interne.
 *
 * @mixin RestaurantBranch
 */
class RestaurantBranchPublicProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'is_public' => (bool) $this->is_public,
            'public_slug' => $this->public_slug,
            'establishment_type' => $this->establishment_type?->value,
            'cuisine_types' => $this->cuisine_types,
            'public_description' => $this->public_description,
            'cover_image_url' => $this->cover_image_url,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'updated_at' => $this->updated_at,
        ];
    }
}
