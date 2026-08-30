<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-401 (#6188) — Représentation API d'une session de caisse POS.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantPosSession
 */
class RestaurantPosSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opened_by_user_id' => $this->opened_by_user_id,
            'closed_by_user_id' => $this->closed_by_user_id,
            'opening_cash_minor' => $this->opening_cash_minor,
            'expected_cash_minor' => $this->expected_cash_minor,
            'counted_cash_minor' => $this->counted_cash_minor,
            'variance_minor' => $this->variance_minor,
            'variance_reason' => $this->variance_reason,
            'status' => $this->status->value,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
