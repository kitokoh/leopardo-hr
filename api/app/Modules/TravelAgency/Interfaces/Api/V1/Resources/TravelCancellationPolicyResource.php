<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-813 (#6103) — Ressource API d'une politique d'annulation.
 */
class TravelCancellationPolicyResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var TravelCancellationPolicy $policy */
        $policy = $this->resource;

        return [
            'id' => $policy->id,
            'trip_id' => $policy->trip_id,
            'class_id' => $policy->class_id,
            'cancel_before_hours' => $policy->cancel_before_hours,
            'penalty_percent' => $policy->penalty_percent,
            'refundable' => $policy->refundable,
            'is_active' => $policy->is_active,
            'description' => $policy->description,
            'created_at' => $policy->created_at?->toIso8601String(),
            'updated_at' => $policy->updated_at?->toIso8601String(),
        ];
    }
}
