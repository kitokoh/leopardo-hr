<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-408 (#6195) — Représentation API d'un remboursement.
 *
 * `reason_text_redacted` est exposé tel quel (aucune PII par construction) ;
 * le payload de callback n'est jamais exposé. Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantRefund
 */
class RestaurantRefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'amount_minor' => $this->amount_minor,
            'reason_code' => $this->reason_code,
            'reason_text' => $this->reason_text_redacted,
            'refunded_by_user_id' => $this->refunded_by_user_id,
            'status' => $this->status->value,
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
