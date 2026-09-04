<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-409 (#6196) — Représentation API d'une session d'occupation de table.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantTableSession
 */
class RestaurantTableSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'table_id' => $this->table_id,
            'order_id' => $this->order_id,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'covers' => $this->covers,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
