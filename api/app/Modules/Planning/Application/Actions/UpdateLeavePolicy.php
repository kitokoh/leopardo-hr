<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\LeavePolicy;

/**
 * Cas d'usage : mise à jour d'une politique de congés par un responsable
 * (principal/rh).
 *
 * Consommé par `PUT /api/v1/leave-policies/{leavePolicy}`
 * (LeavePolicyController::update). L'appartenance au tenant (404) et le rôle
 * (403) sont vérifiés par le contrôleur avant l'appel.
 */
class UpdateLeavePolicy
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(LeavePolicy $leavePolicy, array $validated): LeavePolicy
    {
        $leavePolicy->update($validated);

        return $leavePolicy->fresh() ?? $leavePolicy;
    }
}
