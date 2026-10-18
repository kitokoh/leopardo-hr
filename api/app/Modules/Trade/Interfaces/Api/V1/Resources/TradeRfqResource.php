<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeRfq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Allowlistee : seuls ces champs sortent de l'API. Les lignes et invitations ne
 * sont incluses QUE si la relation a ete chargee (`whenLoaded`), ce qui evite
 * des requetes implicites en cascade sur une liste.
 *
 * @mixin TradeRfq
 */
final class TradeRfqResource extends JsonResource
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
            'title' => $this->title,
            'status' => $this->status->value,
            'destination_country' => $this->destination_country,
            'destination_port' => $this->destination_port,
            'incoterm' => $this->incoterm,
            'budget_minor' => $this->budget_minor,
            'budget_currency' => $this->budget_currency,
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'notes' => $this->notes,
            'published_at' => $this->published_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'awarded_at' => $this->awarded_at?->toIso8601String(),
            'awarded_quote_id' => $this->awarded_quote_id,
            'lines' => TradeRfqLineResource::collection($this->whenLoaded('lines')),
            'invitations' => TradeRfqInvitationResource::collection($this->whenLoaded('invitations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
