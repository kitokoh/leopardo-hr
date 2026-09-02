<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Resources;

use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification destinataire (DELIVERY-206, issue #6290) — RGPD : le numéro
 * est masqué (4 premiers + 2 derniers chiffres) sauf pour les admins.
 *
 * @mixin DeliveryNotification
 */
final class DeliveryNotificationResource extends JsonResource
{
    private bool $maskPhone;

    public function __construct($resource, bool $maskPhone = true)
    {
        parent::__construct($resource);
        $this->maskPhone = $maskPhone;
    }

    public function withMask(bool $mask): self
    {
        $this->maskPhone = $mask;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $phone = (string) $this->recipient_phone;

        return [
            'id' => $this->id,
            'delivery_id' => $this->delivery_id,
            'event_type' => $this->event_type,
            'channel' => $this->channel,
            'recipient_phone' => $this->maskPhone
                ? substr($phone, 0, 4).'…'.substr($phone, -2)
                : $phone,
            'template_key' => $this->template_key,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
