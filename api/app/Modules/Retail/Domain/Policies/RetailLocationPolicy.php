<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des emplacements de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * #9207 — les permissions `retail.*` declarees par le manifest sont
 * desormais CONSOMMEES (elles ne l'etaient pas : toutes les policies se
 * contentaient de `hasManagerRole('principal','rh')`, ce qui interdisait a un
 * vendeur de tenir la caisse sans lui ouvrir la paie et les RH).
 *
 * Ouvrir / supprimer une boutique est une decision de DIRECTION
 * (`retail.settings`, configuration de tenant) ; lecture ouverte aux membres
 * du tenant (scope `company_id` verifie).
 * deny-by-default : aucun role = refus (fail-closed, pattern Catalog #6880).
 */
class RetailLocationPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailLocation $location): bool
    {
        return $location->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->permissions->canManageSettings($actor);
    }

    public function update(Employee $actor, RetailLocation $location): bool
    {
        return $location->company_id === (string) $actor->company_id
            && $this->permissions->canManageSettings($actor);
    }

    public function delete(Employee $actor, RetailLocation $location): bool
    {
        return $this->update($actor, $location);
    }
}
