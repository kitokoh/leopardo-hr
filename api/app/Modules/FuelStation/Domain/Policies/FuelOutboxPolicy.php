<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;

/**
 * RBAC de l'outbox Accounting (FUEL-015) : supervision et rejeu réservés
 * au manager, croisés avec l'accès ressource-scopé (#7600).
 */
class FuelOutboxPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function retry(Employee $actor, FuelOutboxEvent $event): bool
    {
        return $actor->isManager()
            && $this->canManageScopedResource($actor, 'fuel_station', null, true);
    }
}
