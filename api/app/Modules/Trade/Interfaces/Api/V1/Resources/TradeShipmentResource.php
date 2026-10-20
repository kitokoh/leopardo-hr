<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeShipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expedition — TRADE-004/#8479.
 *
 * `tracking_reference` et `eta` proviennent de ce qui a ete SAISI ou recu d'un
 * prestataire : rien n'est deduit d'un service de suivi externe (aucun n'etant
 * souscrit). L'interface peut donc les afficher comme des donnees declarees.
 *
 * @mixin TradeShipment
 */
final class TradeShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trade_order_id' => $this->trade_order_id,
            'mode' => $this->mode->value,
            'carrier_name' => $this->carrier_name,
            'tracking_reference' => $this->tracking_reference,
            'status' => $this->status->value,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'eta' => $this->eta?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
