<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;

/**
 * Issue #1917 — Policy Laravel pour les cotisations sociales.
 *
 * Même matrice que `TaxSlabPolicy` (CONVENTIONS §2.5) :
 *   - SuperAdmin : tout ;
 *   - Manager/principal RH : CRUD + soumission sur SA société ;
 *   - autres : refus.
 */
class SocialContributionPolicy
{
    public function viewAny(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager();
    }

    public function view(Employee|SuperAdmin $actor, SocialContribution $contribution): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $contribution->company_id === $actor->company_id;
    }

    public function create(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager();
    }

    public function update(Employee|SuperAdmin $actor, SocialContribution $contribution): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $contribution->company_id === $actor->company_id;
    }

    public function delete(Employee|SuperAdmin $actor, SocialContribution $contribution): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $contribution->company_id === $actor->company_id;
    }

    public function submit(Employee|SuperAdmin $actor, SocialContribution $contribution): bool
    {
        return $this->update($actor, $contribution);
    }
}
