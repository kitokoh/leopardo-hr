<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpOptOut;

/**
 * RBAC des opt-outs de relance (BC-29 COMMUNICATION, R4 #7689).
 *
 * L'opt-out est un garde-fou PROTECTEUR a l'echelle du tenant : tout
 * employe peut consulter la liste et AJOUTER une exclusion (arreter de
 * relancer quelqu'un ne doit jamais etre bloque) ; la SUPPRESSION (qui
 * re-autorise les relances) est reservee aux managers principal/rh —
 * pattern `CommunicationCategoryPolicy` (R3).
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationFollowUpOptOutPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function delete(Employee $actor, CommunicationFollowUpOptOut $optOut): bool
    {
        return $optOut->company_id === (string) $actor->company_id
            && $actor->role === 'manager'
            && in_array($actor->manager_role, ['principal', 'rh'], true);
    }
}
