<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\ValueObjects\VehiclePositionFreshness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-306 (#6036) — Représentation API d'un véhicule de la flotte.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelVehicle
 */
class TravelVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'registration_number' => $this->registration_number,
            'seat_capacity' => $this->seat_capacity,
            'carrier_id' => $this->carrier_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'position' => $this->positionPayload(),
        ];
    }

    /**
     * #8540 — dernière position connue du véhicule.
     *
     * `null` quand aucun dispositif n'a transmis de position : on n'invente
     * JAMAIS de coordonnées. `is_measured` vaut `false` pour une position
     * estimée, afin qu'elle ne soit pas présentée comme une position GPS.
     *
     * @return array<string, mixed>|null
     */
    private function positionPayload(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $source = $this->position_source;

        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'source' => $source?->value,
            'source_label' => $source?->label(),
            'is_measured' => $source?->isMeasured() ?? false,
            'recorded_at' => $this->position_recorded_at,
            'age_seconds' => $this->position_recorded_at !== null
                ? (int) $this->position_recorded_at->diffInSeconds(now())
                : null,
            // #8548 — fraîcheur opposable : une position ancienne ou absente
            // n'est jamais présentée comme courante.
            'freshness' => VehiclePositionFreshness::fromNullable(
                $this->position_recorded_at,
                $this->staleAfterMinutes(),
            )->toArray(),
        ];
    }

    /**
     * Seuil de fraîcheur explicite (config `travel.fleet_position`), jamais deviné.
     */
    private function staleAfterMinutes(): int
    {
        $configured = config('travel.fleet_position.stale_after_minutes', 30);

        return is_numeric($configured) ? max(1, (int) $configured) : 30;
    }
}
