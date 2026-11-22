<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeProduct;
use App\Modules\Trade\Domain\Models\TradeQuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne d'offre — TRADE-004/#8479, enrichie #9777.
 *
 * `line_total_minor` est le total CALCULE par le serveur : c'est la valeur de
 * reference affichable, jamais un montant fourni par le client.
 *
 * #9777 — `product` expose le produit catalogue selectionne (`null` en saisie
 * libre). Il est **omis** si la relation n'a pas ete chargee, ce qui n'arrive
 * pas via les controleurs (ils chargent tous `lines.product`). Les champs sont
 * volontairement limites a ce qui sert a reconnaitre la marchandise offerte :
 * id, nom, unite, devise, MOQ. `company_id` n'est jamais expose.
 *
 * @mixin TradeQuoteLine
 */
final class TradeQuoteLineResource extends JsonResource
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
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price_minor' => $this->unit_price_minor,
            'moq_quantity' => $this->moq_quantity,
            'lead_time_days' => $this->lead_time_days,
            'line_total_minor' => $this->line_total_minor,
            'position' => $this->position,
        ];
    }
}
