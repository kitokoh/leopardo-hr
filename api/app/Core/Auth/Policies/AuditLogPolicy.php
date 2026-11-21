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
     *
     * #5439 — le manager RH est également lecteur légitime du journal
     * d'audit global (il traite les demandes RGPD « qui a consulté la
     * fiche de X ? »). L'employé reste exclu. Cette règle était portée par
     * l'ancien contrôleur HR (`hasManagerRole('principal', 'rh')`) ; la
     * fusion des routes dans Core (#9732) doit la conserver.
     */
    public function viewTenantAudit(Employee $employee): bool
    {
        return $employee->hasManagerRole('principal', 'rh');
    }
}
