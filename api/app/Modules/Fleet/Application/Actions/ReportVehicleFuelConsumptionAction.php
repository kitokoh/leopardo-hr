<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cas d'usage « rapport de consommation carburant » (BOS-024g, #8218).
 *
 * Extrait de `FleetController::fuelReport` : agregation par vehicule sur la
 * periode demandee par l'appelant, seuls les trajets renseignes en carburant
 * sont comptes.
 */
final class ReportVehicleFuelConsumptionAction
{
    /**
     * @return Collection<int, VehicleTrip>
     */
    public function execute(string $companyId, string $from, string $to): Collection
    {
        return VehicleTrip::query()
            ->where('company_id', $companyId)
            ->whereBetween('start_time', [$from, $to])
            ->whereNotNull('fuel_consumed')
            ->selectRaw('vehicle_id, SUM(fuel_consumed) as total_fuel, SUM(distance_km) as total_distance')
            ->groupBy('vehicle_id')
            ->get();
    }
}
