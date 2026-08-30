<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-601 (#6206) — Représentation API d'une réservation.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantReservation
 */
class RestaurantReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'branch_id' => $this->branch_id,
            'customer_contact_id' => $this->customer_contact_id,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'reserved_at' => $this->reserved_at,
            'covers' => $this->covers,
            'table_id' => $this->table_id,
            'zone_id' => $this->zone_id,
            'status' => $this->status->value,
            'deposit_minor' => $this->deposit_minor,
            'notes_redacted' => $this->notes_redacted,
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
