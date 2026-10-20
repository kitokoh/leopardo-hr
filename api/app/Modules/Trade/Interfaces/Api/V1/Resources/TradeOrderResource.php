<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commande — BC-36 TRADE, TRADE-004/#8479.
 *
 * `total_minor` provient de l'offre acceptee (jamais d'une entree client) ;
 * `direction` (`purchase|sale`) permet a une meme entreprise d'afficher ses
 * commandes d'achat et de vente depuis le meme modele.
 *
 * @mixin TradeOrder
 */
final class TradeOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'direction' => $this->direction->value,
            'trade_deal_id' => $this->trade_deal_id,
            'trade_quote_id' => $this->trade_quote_id,
            'trade_counterparty_id' => $this->trade_counterparty_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'incoterm' => $this->incoterm,
            'total_minor' => $this->total_minor,
            'payment_terms' => $this->payment_terms,
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'expected_delivery_at' => $this->expected_delivery_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'shipments' => TradeShipmentResource::collection($this->whenLoaded('shipments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
