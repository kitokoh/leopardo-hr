<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmChannelConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'une conversation CRM — aucune PII exposée (l'identifiant
 * provider est un hash déterministe, jamais le numéro du client).
 *
 * @mixin CrmChannelConversation
 */
final class CrmChannelConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'status' => $this->status,
            'unread_count' => $this->unread_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
