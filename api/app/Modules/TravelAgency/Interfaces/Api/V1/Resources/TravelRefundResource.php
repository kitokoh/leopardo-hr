<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-808 (#6098) — Représentation API d'un remboursement.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelRefund
 */
class TravelRefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'passenger_id' => $this->passenger_id,
            'amount_minor' => $this->amount_minor,
            'penalty_minor' => $this->penalty_minor,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'refund_key' => $this->refund_key,
            'created_at' => $this->created_at,
        ];
    }
}
