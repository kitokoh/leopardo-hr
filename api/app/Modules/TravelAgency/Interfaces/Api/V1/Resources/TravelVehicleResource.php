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
            // `seat_layout` = configuration BRUTE persistée (null = non configuré).
            'seat_layout' => $this->seat_layout,
            // `effective_seat_layout` = géométrie réellement rendue (repli compris).
            'effective_seat_layout' => $this->seatLayoutPayload(),
            'carrier_id' => $this->carrier_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'position' => $this->positionPayload(),
        ];
    }

    /**
     * #8644 — disposition effective des sièges.
     *
     * `seat_layout` est la configuration RÉELLE (ou `null` si le véhicule n'a
     * jamais été configuré). `effective_seat_layout` est la géométrie qui sera
     * réellement rendue par le plan de sièges : configuration si elle existe,
     * sinon repli par capacité. `is_configured` dit lequel des deux on regarde —
     * le client ne peut donc pas confondre une déduction avec une donnée lue.
     *
     * Aucune donnée personnelle : uniquement des entiers de géométrie.
     *
     * @return array{seats_per_row: int, aisle_after: int|null, absent_seats?: list<int>, is_configured: bool}
     */
    private function seatLayoutPayload(): array
    {
        return $this->resource->effectiveSeatLayout();
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
