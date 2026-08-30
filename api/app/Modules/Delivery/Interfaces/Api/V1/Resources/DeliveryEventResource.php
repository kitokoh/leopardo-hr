<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement de tracking (DELIVERY-204, issue #6288) — allowlisté, payload
 * exposé (proof_document_id) sans clé interne.
 *
 * @mixin \App\Modules\Delivery\Domain\Models\DeliveryEvent
 */
final class DeliveryEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_id' => $this->delivery_id,
            'type' => $this->type,
            'event_at' => $this->event_at?->toIso8601String(),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'origin' => $this->origin,
            'payload' => $this->payload,
        ];
    }
}
