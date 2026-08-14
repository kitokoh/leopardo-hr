<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;

/**
 * Issue #1874 — Policy Laravel pour l'audit des calculs de paie.
 *
 * CONVENTIONS §2.5 (pattern #1917) : chaque action controller passe par une
 * Policy. Matrice :
 *   - SuperAdmin (plateforme) : tout (vue cross-tenant via /admin/payroll/audit) ;
 *   - manager principal/RH du tenant : lecture de l'audit de SA société
 *     (isolation tenant stricte — l'audit d'un autre tenant est refusé) ;
 *   - autres rôles / autre tenant : refus.
 */
class PayrollAuditPolicy
{
    public function viewAny(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->hasManagerRole('principal', 'rh');
    }

    public function view(Employee|SuperAdmin $actor, PayrollCalculationAudit $audit): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->hasManagerRole('principal', 'rh')
            && $audit->company_id === $actor->company_id;
    }
}
