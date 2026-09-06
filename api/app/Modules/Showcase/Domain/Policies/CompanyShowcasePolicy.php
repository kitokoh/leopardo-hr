<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;

/**
 * RBAC de la vitrine publique (BC-27 SHOWCASE, #6865).
 *
 * Gestion (creation 1-clic, edition, publication) reservee au responsable du
 * tenant (sous-roles `principal`/`rh`, spec SOLUTION_SITE_VITRINE.md US1-US6) ;
 * lecture ouverte aux membres du tenant (scope `company_id` verifie).
 * deny-by-default : aucun role = refus.
 */
class CompanyShowcasePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $showcase->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $showcase->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $showcase->company_id === (string) $actor->company_id;
    }

    /**
     * Publication / depublication — meme portee que l'edition (US6, #6871).
     */
    public function publish(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $this->update($actor, $showcase);
    }

    public function unpublish(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $this->update($actor, $showcase);
    }
}
