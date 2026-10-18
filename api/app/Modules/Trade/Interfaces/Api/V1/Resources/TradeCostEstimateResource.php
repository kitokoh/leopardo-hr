<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use App\Modules\Trade\Infrastructure\Services\TradeCustomsChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estimation de cout total rendu — BC-36 TRADE, TRADE-007/#8482.
 *
 * La reponse porte EXPLICITEMENT l'etat de completude : `incomplete` et ses
 * motifs (`unavailable_lines`, `unconverted_lines`, `missing_expected_types`).
 * C'est ce qui permet a l'interface de ne jamais presenter un total partiel
 * comme un prix final — l'exigence d'honnetete du calcul.
 *
 * Le `disclaimer` accompagne chaque reponse : Leopardo aide a l'estimation et a
 * la preparation, il ne rend aucune decision douaniere.
 *
 * @mixin TradeCostEstimate
 */
final class TradeCostEstimateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'trade_deal_id' => $this->trade_deal_id,
            'trade_quote_id' => $this->trade_quote_id,
            'trade_order_id' => $this->trade_order_id,
            'origin_country' => $this->origin_country,
            'destination_country' => $this->destination_country,
            'incoterm' => $this->incoterm,
            'goods_value_minor' => $this->goods_value_minor,
            'goods_currency' => $this->goods_currency,
            'display_currency' => $this->display_currency,
            // Taux TRACE : sa date et sa source voyagent avec la valeur, pour
            // que l'utilisateur sache d'ou vient le chiffre.
            'fx_rate' => $this->fx_rate,
            'fx_rate_date' => $this->fx_rate_date?->toDateString(),
            'fx_source' => $this->fx_source,
            'total_minor' => $this->total_minor,
            'status' => $this->status,
            'is_complete' => $this->status === 'complete',
            'lines' => TradeCostEstimateLineResource::collection($this->whenLoaded('lines')),
            'notes' => $this->notes,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'disclaimer' => TradeCustomsChecklist::DISCLAIMER,
        ];
    }
}
