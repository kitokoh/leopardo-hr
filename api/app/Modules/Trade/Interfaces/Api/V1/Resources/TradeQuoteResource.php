<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Offre commerciale — BC-36 TRADE, TRADE-004/#8479.
 *
 * Les totaux exposes (`subtotal_minor`, `total_minor`) sont ceux FIGES par le
 * serveur : le client ne peut pas les influencer.
 *
 * @mixin TradeQuote
 */
final class TradeQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'version' => $this->version,
            'supersedes_id' => $this->supersedes_id,
            'direction' => $this->direction->value,
            'trade_rfq_id' => $this->trade_rfq_id,
            'trade_rfq_invitation_id' => $this->trade_rfq_invitation_id,
            'trade_deal_id' => $this->trade_deal_id,
            'trade_counterparty_id' => $this->trade_counterparty_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'incoterm' => $this->incoterm,
            'validity_until' => $this->validity_until?->toDateString(),
            'lead_time_days' => $this->lead_time_days,
            'payment_terms' => $this->payment_terms,
            'subtotal_minor' => $this->subtotal_minor,
            'total_minor' => $this->total_minor,
            'notes' => $this->notes,
            'lines' => TradeQuoteLineResource::collection($this->whenLoaded('lines')),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
