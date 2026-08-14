<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PublicHoliday;

/**
 * Issue #1917 — Policy Laravel pour les jours fériés.
 *
 * Remplace `companyScope()`/`authorizeWrite()` inline de
 * `PublicHolidayController`. Matrice :
 *   - SuperAdmin : tout (fériés NATIONAUX, company_id = null) ;
 *   - Principal RH du tenant : lecture/écriture sur les fériés de SA
 *     société (company_id = sa société), jamais un férié national ;
 *   - autres : refus.
 */
class PublicHolidayPolicy
{
    public function viewAny(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isPrincipal();
    }

    public function create(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isPrincipal();
    }

    public function update(Employee|SuperAdmin $actor, PublicHoliday $holiday): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isPrincipal()
            && $holiday->company_id !== null
            && $holiday->company_id === $actor->company_id;
    }

    public function delete(Employee|SuperAdmin $actor, PublicHoliday $holiday): bool
    {
        return $this->update($actor, $holiday);
    }
}
