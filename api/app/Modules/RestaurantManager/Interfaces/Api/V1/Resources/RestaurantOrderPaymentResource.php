<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-407 (#6194) — Représentation API d'un paiement de commande.
 *
 * `callback_payload_redacted` n'est JAMAIS exposé (donnée sensible).
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantOrderPayment
 */
class RestaurantOrderPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'pos_session_id' => $this->pos_session_id,
            'provider_code' => $this->provider_code->value,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at,
            'provider_reference' => $this->provider_reference,
            'tip_minor' => $this->tip_minor,
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
