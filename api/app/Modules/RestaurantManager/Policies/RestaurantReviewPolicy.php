<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-902 (#7747) — Policy des avis clients (modération).
 *
 * Lecture de la file de modération : tout employé authentifié du tenant
 * (listing scopé par succursales accessibles via
 * ScopesRestaurantBranchListings, périmètre `company_id` par le scope
 * BelongsToCompany). Publication/rejet : geste de GESTION de la succursale
 * (pattern gérant, `canManageBranchResource` — ChecksRestaurantBranchAccess
 * #7599 : principal/rh tant que le scoping ressource n'est pas actif, puis
 * niveau `manage` sur LA succursale de l'avis).
 */
class RestaurantReviewPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantReview $review): bool
    {
        return $review->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $review->branch_id);
    }

    /** Publication ou rejet d'un avis (modération gérant). */
    public function moderate(Employee $actor, RestaurantReview $review): bool
    {
        return $review->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $review->branch_id);
    }
}
