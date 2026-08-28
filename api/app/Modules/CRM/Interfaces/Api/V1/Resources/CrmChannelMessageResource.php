<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'un message de canal CRM.
 *
 * PII masquée par défaut (convention CRM #5713) : le corps et les adresses
 * (to/from) ne sont JAMAIS exposés — seules les métadonnées de délivrabilité
 * et de coût sont visibles. Un endpoint dédié (audit, autorisation
 * explicite) pourra exposer le contenu plus tard.
 *
 * @mixin CrmChannelMessage
 */
final class CrmChannelMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'conversation_id' => $this->conversation_id,
            'provider' => $this->provider,
            'direction' => $this->direction,
            'template_name' => $this->template_name,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'error_code' => $this->error_code,
            'cost' => $this->cost,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
