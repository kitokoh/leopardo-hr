<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailLocation;

/**
 * RBAC des emplacements de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * Gestion réservée au responsable du tenant (sous-rôles `principal`/`rh`) ;
 * lecture ouverte aux membres du tenant (scope `company_id` vérifié).
 * deny-by-default : aucun rôle = refus (fail-closed, pattern Catalog #6880).
 */
class RetailLocationPolicy
{
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
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RetailLocation $location): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $location->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, RetailLocation $location): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $location->company_id === (string) $actor->company_id;
    }
}
