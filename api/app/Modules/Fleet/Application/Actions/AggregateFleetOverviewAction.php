<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAlert;

/**
 * Cas d'usage « tableau de bord de flotte » (BOS-024g, #8218).
 *
 * Extrait de `FleetController::overview` : repartition des vehicules par
 * statut et nombre d'alertes non acquittees du tenant. Les compteurs sont
 * calcules en base (jamais en memoire) — la reponse HTTP est inchangee.
 */
final class AggregateFleetOverviewAction
{
    /**
     * @return array{total_vehicles: int, active: int, in_maintenance: int, decommissioned: int, unacknowledged_alerts: int}
     */
    public function execute(string $companyId): array
    {
        return [
            'total_vehicles' => Vehicle::query()->where('company_id', $companyId)->count(),
            'active' => Vehicle::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'in_maintenance' => Vehicle::query()->where('company_id', $companyId)->where('status', 'maintenance')->count(),
            'decommissioned' => Vehicle::query()->where('company_id', $companyId)->where('status', 'decommissioned')->count(),
            'unacknowledged_alerts' => VehicleAlert::query()
                ->where('company_id', $companyId)
                ->where('acknowledged', false)
                ->count(),
        ];
    }
}
