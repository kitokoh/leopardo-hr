<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cas d'usage « rapport de kilometrage » (BOS-024g, #8218).
 *
 * Extrait de `FleetController::mileageReport` : kilometres, nombre de trajets
 * et vitesse moyenne par vehicule sur la periode demandee par l'appelant.
 */
final class ReportVehicleMileageAction
{
    /**
     * @return Collection<int, VehicleTrip>
     */
    public function execute(string $companyId, string $from, string $to): Collection
    {
        return VehicleTrip::query()
            ->where('company_id', $companyId)
            ->whereBetween('start_time', [$from, $to])
            ->selectRaw('vehicle_id, SUM(distance_km) as total_km, COUNT(*) as trip_count, AVG(avg_speed_kmh) as avg_speed')
            ->groupBy('vehicle_id')
            ->get();
    }
}
