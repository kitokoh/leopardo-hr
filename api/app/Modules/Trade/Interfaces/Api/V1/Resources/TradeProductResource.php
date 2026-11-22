<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Produit du catalogue B2B — BC-36 TRADE, #9767.
 *
 * ## Ce que cette ressource ne fait pas
 *
 * Elle ne calcule rien. `indicative_unit_price_minor` est un entier en minor
 * units tel que saisi : le catalogue n'est pas une offre commerciale, il ne
 * porte ni remise ni total. Un prix ferme n'existe que dans `TradeQuote`, où
 * le serveur recalcule les totaux depuis les lignes.
 *
 * `hs_code` est exposé ici (espace interne authentifié) mais **jamais** par la
 * ressource publique : c'est une donnée de conformité, pas un argument de
 * vente.
 *
 * @mixin TradeProduct
 */
final class TradeProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'hs_code' => $this->hs_code,
            'origin_country' => $this->origin_country,
            'unit' => $this->unit,
            'moq_quantity' => $this->moq_quantity,
            'indicative_unit_price_minor' => $this->indicative_unit_price_minor,
            'currency' => $this->currency,
            'specs' => $this->specs ?? [],
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
