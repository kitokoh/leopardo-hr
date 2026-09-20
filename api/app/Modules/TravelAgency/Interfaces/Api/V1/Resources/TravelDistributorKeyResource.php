<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-DISTRIBUTION (#7641) — représentation API d'une clé distributeur.
 *
 * Interne au module (PA2-ARCH-010). Le hash n'est JAMAIS exposé — le token
 * brut n'apparaît qu'une fois dans les réponses de création/rotation
 * (champ `api_key`, hors de cette resource).
 *
 * @mixin TravelDistributorKey
 */
class TravelDistributorKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scopes' => $this->scopes,
            'enabled' => $this->enabled,
            'last_used_at' => $this->last_used_at,
            'usage_count' => $this->usage_count,
            'rotated_at' => $this->rotated_at,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }
}
