<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Issue #5719 — Politique d'accès à la recherche CRM client.
 *
 * Défense en profondeur derrière le middleware `api.manager:principal,rh,
 * marketing` : seul un manager du tenant (avec un rôle CRM autorisé) peut
 * lancer une recherche. Un employé ordinaire ou un manager d'un autre rôle
 * reçoit 403. L'accès reste strictement tenant-scoped (les modèles appliquent
 * le scope `company_id`).
 */
class CrmSearchPolicy
{
    use HandlesAuthorization;

    public function search(?Employee $user): bool
    {
        return $user !== null
            && $user->company_id !== null
            && $user->isManager()
            && $user->hasManagerRole('principal', 'rh', 'marketing');
    }
}
