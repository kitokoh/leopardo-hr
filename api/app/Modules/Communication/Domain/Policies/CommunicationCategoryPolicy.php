<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationCategory;

/**
 * RBAC de la taxonomie email du tenant (BC-29 COMMUNICATION, R3 #7688).
 *
 * La taxonomie est une donnee de TENANT (pas de boite) : tout employe du
 * tenant la CONSULTE (les categories s'affichent sur ses messages) ; seuls
 * les managers principal/rh la MODIFIENT (creation, renommage,
 * activation/desactivation, suppression des categories non systeme) —
 * pattern des reglages d'entreprise.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationCategoryPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function create(Employee $actor): bool
    {
        return $this->managesTaxonomy($actor);
    }

    public function update(Employee $actor, CommunicationCategory $category): bool
    {
        return $category->company_id === (string) $actor->company_id
            && $this->managesTaxonomy($actor);
    }

    public function delete(Employee $actor, CommunicationCategory $category): bool
    {
        // Les categories systeme (defauts) se desactivent mais ne se
        // suppriment pas — la classification historique reste lisible.
        return $category->company_id === (string) $actor->company_id
            && ! $category->is_system
            && $this->managesTaxonomy($actor);
    }

    private function managesTaxonomy(Employee $actor): bool
    {
        return $actor->role === 'manager'
            && in_array((string) $actor->manager_role, ['principal', 'rh'], true);
    }
}
