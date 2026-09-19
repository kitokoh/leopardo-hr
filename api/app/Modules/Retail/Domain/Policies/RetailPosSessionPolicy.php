<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailPosSession;

/**
 * RBAC des sessions de caisse POS du module Retail (BC-17 RETAIL, #7674).
 *
 * Lecture ouverte aux membres du tenant (scope `company_id` verifie) ;
 * ouverture et cloture de caisse reservees au responsable du tenant
 * (sous-roles `principal`/`rh`) — miroir du comportement historique du POS
 * RestaurantManager (ChecksRestaurantBranchAccess sans assignation, #7599)
 * et des ecritures Retail existantes (#7672/#7673).
 * deny-by-default : aucun role = refus (fail-closed, pattern Catalog #6880).
 */
class RetailPosSessionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailPosSession $session): bool
    {
        return $session->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function close(Employee $actor, RetailPosSession $session): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $session->company_id === (string) $actor->company_id;
    }
}
