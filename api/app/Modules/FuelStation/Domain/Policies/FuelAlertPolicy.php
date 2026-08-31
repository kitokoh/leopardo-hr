<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;

/**
 * RBAC des alertes & préférences FuelStation (FUEL-019, #5813).
 *
 * - Manager : lecture/ack/resolution des alertes, gestion des préférences.
 * - Employé : deny-by-default.
 */
class FuelAlertPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelAlert $alert): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelAlert $alert): bool
    {
        return $actor->isManager();
    }

    public function managePreferences(Employee $actor, FuelNotificationPreference $preference): bool
    {
        return $actor->isManager();
    }
}
