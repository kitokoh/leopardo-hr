<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST-OS-3 (Mission Leopardo Restaurant OS) — Représentation API d'un travail
 * d'impression.
 *
 * Aucun secret, aucun payload ESC/POS dans cette ressource (les octets ne
 * sortent que par la surface bridge dédiée et authentifiée). `payload_hash`
 * permet au bridge de détecter un contenu modifié sans transporter les octets.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantPrintJob
 */
class RestaurantPrintJobResource extends JsonResource
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
            'status_label' => $this->status->label(),
            'attempts' => $this->attempts,
            'printer_ref' => $this->printer_ref,
            'last_error' => $this->last_error,
            'payload_hash' => $this->payload_hash,
            'claimed_at' => $this->claimed_at,
            'printed_at' => $this->printed_at,
            'failed_at' => $this->failed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
