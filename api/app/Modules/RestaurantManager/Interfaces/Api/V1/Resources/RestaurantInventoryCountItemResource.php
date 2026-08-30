<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-504 (#6203) — Ressource API d'une ligne de comptage d'inventaire.
 */
class RestaurantInventoryCountItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'count_id' => $this->count_id,
            'ingredient_id' => $this->ingredient_id,
            'expected_qty' => $this->expected_qty,
            'counted_qty' => $this->counted_qty,
            'variance_qty' => $this->variance_qty,
            'reason_code' => $this->reason_code,
        ];
    }
}
