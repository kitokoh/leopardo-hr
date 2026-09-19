<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;

/**
 * HC-007 (#7791) — Policy du catalogue d'actes médicaux (BC-30),
 * deny-by-default.
 *
 * `health.billing` (comptable) et `health.admin` (direction) gèrent le
 * catalogue tarifaire — critère d'acceptation HC-007. Réception,
 * praticiens et employé lambda : 403 (le tarif des soins est une donnée
 * de gestion, pas une donnée opérationnelle).
 */
class HealthCareActPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canManageBilling($actor);
    }

    public function view(Employee $actor, HealthCareAct $careAct): bool
    {
        return $this->viewAny($actor) && $careAct->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canManageBilling($actor);
    }

    public function update(Employee $actor, HealthCareAct $careAct): bool
    {
        return $this->create($actor) && $careAct->company_id === $actor->company_id;
    }
}
