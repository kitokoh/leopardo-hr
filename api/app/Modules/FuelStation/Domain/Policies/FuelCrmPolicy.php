<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelAccountVisit;
use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;

/**
 * RBAC du CRM comptes professionnels — gestion manager, croisée avec
 * l'accès ressource-scopé par station (#7600).
 */
class FuelCrmPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAnyAccount(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function viewAccount(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $actor->isManager()
            && $this->canViewScopedResource($actor, 'fuel_station', $account->station_id ?? null, true);
    }

    public function createAccount(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function recordVisit(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $this->viewAccount($actor, $account);
    }

    public function viewVisits(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $this->viewAccount($actor, $account);
    }

    public function updateConsents(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $this->viewAccount($actor, $account);
    }

    public function viewVisit(Employee $actor, FuelAccountVisit $visit): bool
    {
        return $actor->isManager()
            && $this->canViewScopedResource($actor, 'fuel_station', $visit->station_id ?? null, true);
    }
}
