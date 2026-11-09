<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\Retail\Domain\Models\RetailPosSession;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des sessions de caisse POS du module Retail (BC-17 RETAIL, #7674).
 *
 * #9207 — permission `retail.pos` reellement appliquee. Ouvrir et cloturer
 * une caisse est un geste de VENDEUR : direction, ou niveau `operate` sur la
 * boutique concernee (`retail_location`, epique #7597). Avant ce correctif,
 * seul `principal`/`rh` pouvait tenir une caisse.
 *
 * Lecture ouverte aux membres du tenant (scope `company_id` verifie).
 * deny-by-default : aucun role = refus (fail-closed, pattern Catalog #6880).
 */
class RetailPosSessionPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

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
        return $this->permissions->canOperatePos($actor);
    }

    public function close(Employee $actor, RetailPosSession $session): bool
    {
        return $session->company_id === (string) $actor->company_id
            && $this->permissions->canOnLocation(
                $actor,
                (int) $session->location_id,
                EmployeeResourceAssignment::LEVEL_OPERATE,
            );
    }
}
