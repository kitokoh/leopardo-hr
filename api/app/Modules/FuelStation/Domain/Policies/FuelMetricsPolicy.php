<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC des métriques d'observabilité FuelStation (FUEL-020, #5814).
 *
 * Manager uniquement (matériel de pilotage : profondeur de file, alertes
 * ouvertes, staleness des read models) — deny-by-default.
 */
class FuelMetricsPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
