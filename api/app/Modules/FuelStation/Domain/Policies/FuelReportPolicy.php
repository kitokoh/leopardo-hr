<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelReportExport;

/**
 * RBAC du reporting opérationnel (FUEL-017, #5811). deny-by-default :
 * rapports et exports réservés aux managers — croisé avec l'accès
 * ressource-scopé par station (#7600).
 */
class FuelReportPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function export(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function createExport(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function download(Employee $actor, FuelReportExport $export): bool
    {
        return $actor->isManager()
            && $this->canManageScopedResource($actor, 'fuel_station', $export->station_id ?? null, true);
    }
}
