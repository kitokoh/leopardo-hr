<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Resources;

use App\Modules\Delivery\Domain\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource d'une livraison (DELIVERY-201, issue #6285) — allowlistée :
 * seuls les champs métier sont exposés, jamais les clés d'idempotence.
 *
 * @mixin Delivery
 */
final class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Delivery $delivery */
        $delivery = $this->resource;

        return [
            'id' => $delivery->id,
            'reference' => $delivery->reference,
            'source' => $delivery->source,
            'source_reference' => $delivery->source_reference,
            'type' => $delivery->type,
            'status' => $delivery->status,
            'weight_grams' => $delivery->weight_grams,
            'volume_cm3' => $delivery->volume_cm3,
            'declared_value_minor' => $delivery->declared_value_minor,
            'cod_amount_minor' => $delivery->cod_amount_minor,
            'pickup_contact' => $delivery->pickup_contact,
            'pickup_address' => $delivery->pickup_address,
            'dropoff_contact' => $delivery->dropoff_contact,
            'dropoff_phone' => $delivery->dropoff_phone,
            'dropoff_address' => $delivery->dropoff_address,
            'window_from' => $delivery->window_from?->toIso8601String(),
            'window_to' => $delivery->window_to?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }
}
