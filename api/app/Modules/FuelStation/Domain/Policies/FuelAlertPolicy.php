<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;

/**
 * RBAC des alertes FuelStation — gestion manager, croisée avec l'accès
 * ressource-scopé par station (#7600).
 */
class FuelAlertPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function view(Employee $actor, FuelAlert $alert): bool
    {
        return $actor->isManager()
            && $this->canViewScopedResource($actor, 'fuel_station', $alert->station_id ?? null, true);
    }

    public function update(Employee $actor, FuelAlert $alert): bool
    {
        return $actor->isManager()
            && $this->canManageScopedResource($actor, 'fuel_station', $alert->station_id ?? null, true);
    }

    public function managePreferences(Employee $actor, FuelNotificationPreference $preference): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }
}
