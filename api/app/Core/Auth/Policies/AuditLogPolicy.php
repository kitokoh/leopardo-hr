<?php

declare(strict_types=1);

namespace App\Core\Auth\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Policy pour la consultation de la piste d'audit tenant (#9333).
 */
class AuditLogPolicy
{
    /**
     * Le principal peut consulter l'audit de son tenant.
     * Un grant dédié pourra être ajouté plus tard si besoin.
     */
    public function viewTenantAudit(Employee $employee): bool
    {
        return strtolower($employee->role ?? '') === 'manager'
            && strtolower($employee->manager_role ?? '') === 'principal';
    }
}
