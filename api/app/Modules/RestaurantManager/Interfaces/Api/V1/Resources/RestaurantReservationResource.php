<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-601 (#6206) — Ressource API d'une réservation.
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
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'reference' => $this->reference,
            'customer_contact_id' => $this->customer_contact_id,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'reserved_at' => $this->reserved_at?->toIso8601String(),
            'covers' => $this->covers,
            'table_id' => $this->table_id,
            'zone_id' => $this->zone_id,
            'status' => $this->status,
            'deposit_minor' => $this->deposit_minor,
            'notes_redacted' => $this->notes_redacted,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
