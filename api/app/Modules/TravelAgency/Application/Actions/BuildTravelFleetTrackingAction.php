<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\ValueObjects\VehiclePositionFreshness;
use Illuminate\Support\Carbon;

/**
 * #8548 (TRAVEL-OS) — tableau de bord de suivi de la flotte.
 *
 * RÉUTILISE la donnée déjà livrée par #8540 (`travel_vehicles.latitude`,
 * `longitude`, `position_source`, `position_recorded_at`) : aucune nouvelle
 * table, aucun nouveau système de localisation, aucun appel à un fournisseur.
 *
 * Ce que l'action ajoute — et qui manquait — est la LECTURE HONNÊTE :
 *  - la fraîcheur de la dernière position connue (`VehiclePositionFreshness`),
 *    pour qu'une donnée ancienne ou absente ne soit jamais présentée comme
 *    une position courante ;
 *  - la distinction opposable mesure / estimation (`is_measured`) ;
 *  - les départs à venir auxquels le véhicule est affecté.
 *
 * Aucune coordonnée n'est inventée : un véhicule sans dispositif transmetteur
 * reste `position: null` avec `freshness.state = unavailable`.
 */
final class BuildTravelFleetTrackingAction
{
    /**
     * @return array{
     *     vehicles: list<array<string, mixed>>,
     *     summary: array<string, int>,
     *     stale_after_minutes: int
     * }
     */
    public function execute(?int $vehicleId = null): array
    {
        $staleAfterMinutes = $this->staleAfterMinutes();
        $departuresLimit = $this->departuresLimit();

        $query = TravelVehicle::query()->orderBy('code');

        if ($vehicleId !== null) {
            $query->whereKey($vehicleId);
        }

        $vehicles = $query->get();

        $rows = [];
        $summary = [
            'vehicles' => 0,
            'with_position' => 0,
            'current' => 0,
            'stale' => 0,
            'unavailable' => 0,
            'measured' => 0,
            'estimated' => 0,
        ];

        foreach ($vehicles as $vehicle) {
            $freshness = VehiclePositionFreshness::fromNullable(
                $vehicle->position_recorded_at,
                $staleAfterMinutes,
            );

            $hasPosition = $vehicle->latitude !== null && $vehicle->longitude !== null;
            $source = $vehicle->position_source;
            $isMeasured = VehiclePositionFreshness::isMeasuredSource($source);

            $summary['vehicles']++;

            if ($hasPosition) {
                $summary['with_position']++;
            }

            if ($freshness->isUnavailable()) {
                $summary['unavailable']++;
            } elseif ($freshness->isStale()) {
                $summary['stale']++;
            } else {
                $summary['current']++;
            }

            if ($isMeasured) {
                $summary['measured']++;
            } elseif ($source !== null) {
                $summary['estimated']++;
            }

            $rows[] = [
                'id' => $vehicle->id,
                'code' => $vehicle->code,
                'registration_number' => $vehicle->registration_number,
                'status' => $vehicle->status,
                'position' => $hasPosition ? [
                    'latitude' => (float) $vehicle->latitude,
                    'longitude' => (float) $vehicle->longitude,
                    'source' => $source?->value,
                    'source_label' => $source?->label(),
                    // Une estimation n'est JAMAIS présentée comme un GPS.
                    'is_measured' => $isMeasured,
                    'recorded_at' => $vehicle->position_recorded_at,
                ] : null,
                'freshness' => $freshness->toArray(),
                'upcoming_departures' => $this->upcomingDepartures($vehicle, $departuresLimit),
            ];
        }

        return [
            'vehicles' => $rows,
            'summary' => $summary,
            'stale_after_minutes' => $staleAfterMinutes,
        ];
    }

    /**
     * Départs à venir affectés au véhicule (trajets publiés/planifiés dont la
     * date de départ n'est pas passée). Lecture bornée : jamais l'historique
     * complet, jamais de N+1 (une requête par véhicule, plafonnée).
     *
     * @return list<array<string, mixed>>
     */
    private function upcomingDepartures(TravelVehicle $vehicle, int $limit): array
    {
        $today = Carbon::now()->startOfDay();

        $trips = TravelTrip::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [TripStatus::PUBLISHED->value, TripStatus::SCHEDULED->value])
            ->whereDate('departure_date', '>=', $today)
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->limit($limit)
            ->get();

        $departures = [];

        foreach ($trips as $trip) {
            $departures[] = [
                'id' => $trip->id,
                'code' => $trip->code,
                'departure_date' => $trip->departure_date->toDateString(),
                'departure_time' => $trip->departure_time,
                'status' => $trip->status->value,
                'status_label' => $trip->status->label(),
            ];
        }

        return $departures;
    }

    private function staleAfterMinutes(): int
    {
        $configured = config('travel.fleet_position.stale_after_minutes', 30);

        return is_numeric($configured) ? max(1, (int) $configured) : 30;
    }

    private function departuresLimit(): int
    {
        $configured = config('travel.fleet_position.upcoming_departures_limit', 5);

        return is_numeric($configured) ? max(1, min(50, (int) $configured)) : 5;
    }
}
