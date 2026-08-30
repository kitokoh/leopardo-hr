<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;

/**
 * RBAC des incidents FuelStation (FUEL-010, #5804).
 *
 * - Tout employé authentifié du tenant peut SIGNALER un incident (report
 *   terrain — `create`) ; le contexte station est verrouillé par la FK
 *   composite (station_id, company_id) et le 404 sûr cross-tenant.
 * - La gestion (lecture globale, transitions assign/start/resolve/close)
 *   est réservée au manager (deny-by-default).
 */
class FuelIncidentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function update(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function manage(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
