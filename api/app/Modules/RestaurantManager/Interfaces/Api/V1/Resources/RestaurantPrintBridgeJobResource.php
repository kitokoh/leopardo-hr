<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST-OS-3 (Mission Leopardo Restaurant OS) — Vue compacte d'un travail pour
 * le bridge d'impression local (file de récupération).
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantPrintJob
 */
class RestaurantPrintBridgeJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'order_id' => $this->order_id,
            'ticket_kind' => $this->ticket_kind->value,
            'revision' => $this->revision,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'printer_ref' => $this->printer_ref,
            'payload_hash' => $this->payload_hash,
            'created_at' => $this->created_at,
        ];
    }
}
