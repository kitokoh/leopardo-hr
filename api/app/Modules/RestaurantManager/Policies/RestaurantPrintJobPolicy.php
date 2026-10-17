<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * REST-OS-4 (Mission Leopardo Restaurant OS) — Policy des travaux
 * d'impression thermique.
 *
 * Réutilise l'autorisation ressource-scopée (#7599) : la succursale est
 * l'objet d'autorisation. La réimpression est un geste OPÉRATIONNEL (niveau
 * `operate` sur la succursale) ; la lecture suit le niveau `view`. Fail-closed :
 * dès que le scoping est actif, un non-assigné ne voit ni ne réimprime.
 */
class RestaurantPrintJobPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPrintJob $job): bool
    {
        return $job->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $job->branch_id);
    }

    /**
     * Réimpression / relance d'un ticket cuisine (bridge ou écran cuisine).
     */
    public function requeue(Employee $actor, RestaurantPrintJob $job): bool
    {
        return $job->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $job->branch_id);
    }
}
