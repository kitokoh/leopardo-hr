<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\LeavePolicy;

/**
 * Cas d'usage : désactivation d'une politique de congés par un responsable
 * (principal/rh).
 *
 * Consommé par `DELETE /api/v1/leave-policies/{leavePolicy}`
 * (LeavePolicyController::destroy). La suppression est une désactivation
 * douce (`active = false`) : l'historique des soldes/accumulations reste
 * intègre. L'appartenance au tenant (404) et le rôle (403) sont vérifiés par
 * le contrôleur avant l'appel.
 */
class DeactivateLeavePolicy
{
    public function execute(LeavePolicy $leavePolicy): void
    {
        $leavePolicy->update(['active' => false]);
    }
}
