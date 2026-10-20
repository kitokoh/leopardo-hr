<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeRfqInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invitation d'une RFQ — TRADE-003/#8478.
 *
 * ⚠️ AUCUNE EXPOSITION DU TOKEN : le hash (`response_token_hash`) n'est jamais
 * serialise, et la valeur en clair n'est retournee qu'une seule fois par
 * `SendRfqAction`, hors de cette ressource. Une invitation consulteable ne
 * permet donc pas de reconstituer un lien de reponse.
 *
 * @mixin TradeRfqInvitation
 */
final class TradeRfqInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trade_counterparty_id' => $this->trade_counterparty_id,
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'message' => $this->message,
            // On expose seulement l'EXISTENCE et l'expiration du lien, jamais
            // le materiel permettant de l'utiliser.
            'has_response_link' => $this->response_token_hash !== null,
            'response_link_expires_at' => $this->response_token_expires_at?->toIso8601String(),
            'response_link_usable' => $this->isResponseLinkUsable(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
