<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;

/**
 * Issue #1874 — Policy Laravel pour l'audit des calculs de paie.
 *
 * Matrice :
 *   - SuperAdmin (plateforme) : consultation globale (filtre par entreprise) ;
 *   - Manager/principal RH du tenant : audit des calculs de SA société
 *     (isolation tenant) ;
 *   - autres rôles : refus.
 */
class PayrollCalculationAuditPolicy
{
    public function viewAny(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager();
    }

    public function view(Employee|SuperAdmin $actor, PayrollCalculationAudit $audit): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $audit->company_id === $actor->company_id;
    }
}
