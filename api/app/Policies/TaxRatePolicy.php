<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Auth\Access\Response;

/**
 * Issue #1917 — taux légaux et barèmes (tax_slabs + social_contributions).
 *
 * Une seule policy couvre les trois contrôleurs qui manipulent les taux
 * légaux (CONVENTIONS §2.5 : chaque action controller DOIT passer par une
 * Policy Laravel enregistrée) :
 *
 *  - `TaxSlabController` / `SocialContributionController` (guard tenant) :
 *    l'accès est réservé aux managers (le middleware de route
 *    `api.manager:principal,comptable` affine ensuite principal/comptable) ;
 *  - `RateValidationAdminController` (guard `super_admin_api`) : l'approbation
 *    / le rejet d'une ligne soumise restent réservés au SuperAdmin plateforme.
 *
 * L'isolation tenant (ligne d'une autre entreprise → 404) reste assurée par
 * le scope global `BelongsToCompany` et les gardes 404 des contrôleurs,
 * conformément au comportement historique (la policy porte la porte rôle,
 * les 404 cross-company restent inchangés).
 */
class TaxRatePolicy
{
    public function viewAny(Employee|SuperAdmin $actor): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    public function create(Employee|SuperAdmin $actor): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    public function view(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    public function update(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    public function delete(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    /**
     * Soumission d'une ligne draft pour validation (#1813).
     */
    public function submit(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    /**
     * Historique immuable d'une ligne (audit trail #1813).
     */
    public function history(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): bool
    {
        return $this->isTenantManagerOrPlatformAdmin($actor);
    }

    /**
     * Approbation d'une ligne en attente — réservée au SuperAdmin plateforme
     * (message i18n `errors.FORBIDDEN` conservé, cf. `assertPlatformAdmin`).
     */
    public function approve(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): Response
    {
        return $this->platformAdminOnly($actor);
    }

    /**
     * Rejet d'une ligne en attente — réservé au SuperAdmin plateforme.
     */
    public function reject(Employee|SuperAdmin $actor, TaxSlab|SocialContribution $rate): Response
    {
        return $this->platformAdminOnly($actor);
    }

    private function isTenantManagerOrPlatformAdmin(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager();
    }

    private function platformAdminOnly(Employee|SuperAdmin $actor): Response
    {
        return $actor instanceof SuperAdmin
            ? Response::allow()
            : Response::deny(__('errors.FORBIDDEN'));
    }
}
