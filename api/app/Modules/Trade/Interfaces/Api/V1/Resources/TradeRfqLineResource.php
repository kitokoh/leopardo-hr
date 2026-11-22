<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeProduct;
use App\Modules\Trade\Domain\Models\TradeRfqLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de RFQ — TRADE-003/#8478, enrichie #9777.
 *
 * `trade_product_id` peut etre null (saisie libre) : l'API expose donc AUSSI la
 * description, qui est dans tous les cas la source de verite a l'affichage.
 *
 * #9777 — quand la ligne reference un produit du catalogue, `product` porte
 * les champs utiles a l'ecran (nom, unite, devise, MOQ). Sans cela l'ecran ne
 * recevait qu'un entier opaque et ne pouvait pas afficher ce qui est demande.
 *
 * `product` est `null` quand la ligne est en saisie libre. Il est **omis** de
 * la charge utile si la relation n'a pas ete chargee (`whenLoaded` renvoie une
 * MissingValue) — les controleurs chargent `lines.product` sur tous les
 * chemins, donc en pratique la cle est toujours presente.
 *
 * `company_id` du produit n'est JAMAIS expose : le perimetre tenant est une
 * affaire serveur, pas une donnee de ligne.
 *
 * @mixin TradeRfqLine
 */
final class TradeRfqLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trade_product_id' => $this->trade_product_id,
            'product' => $this->whenLoaded('product', fn (): ?array => $this->product instanceof TradeProduct ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
                'currency' => $this->product->currency,
            ] : null),
            'description' => $this->description,
            'specs' => $this->specs,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'target_unit_price_minor' => $this->target_unit_price_minor,
            'position' => $this->position,
        ];
    }
}
