<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\VehicleMaintenance;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cas d'usage « echeances de maintenance a venir » (BOS-024g, #8218).
 *
 * Extrait de `FleetController::maintenanceDue` : interventions dont la
 * prochaine echeance tombe dans la fenetre glissante (30 jours par defaut),
 * ordonnees de la plus proche a la plus lointaine, vehicule precharge pour
 * l'affichage (relation bornee aux 4 champs utilises).
 */
final class ListVehiclesDueForMaintenanceAction
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /**
     * @return Collection<int, VehicleMaintenance>
     */
    public function execute(string $companyId, int $windowDays = self::DEFAULT_WINDOW_DAYS): Collection
    {
        return VehicleMaintenance::query()
            ->where('company_id', $companyId)
            ->whereNotNull('next_service_date')
            ->where('next_service_date', '<=', now()->addDays($windowDays)->toDateString())
            ->with('vehicle:id,plate_number,brand,model')
            ->orderBy('next_service_date')
            ->get();
    }
}
