<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelAccountVisit;
use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;

/**
 * RBAC de l'intégration CRM FuelStation (FUEL-016, #5810).
 *
 * Deny-by-default : la gestion des comptes professionnels, visites et
 * consentements est réservée au manager (rôle commercial/principal). Un
 * pompiste n'accède à aucun endpoint CRM — il ne manipule que son périmètre
 * opérationnel (ventes, relevés, incidents).
 */
class FuelCrmPolicy
{
    public function viewAnyAccount(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewAccount(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $actor->isManager();
    }

    public function createAccount(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function recordVisit(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $actor->isManager();
    }

    public function viewVisits(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $actor->isManager();
    }

    public function updateConsents(Employee $actor, FuelProfessionalAccount $account): bool
    {
        return $actor->isManager();
    }

    public function viewVisit(Employee $actor, FuelAccountVisit $visit): bool
    {
        return $actor->isManager();
    }
}
