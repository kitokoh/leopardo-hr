<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des reglages de la boutique en ligne Leopardo Marche
 * (BC-17 RETAIL, #7807).
 *
 * #9207 — permission `retail.settings` reellement appliquee : activer la
 * marketplace et publier les reglages est de la CONFIGURATION de tenant
 * (direction uniquement — aucune assignation operationnelle ne l'ouvre).
 * Lecture ouverte aux membres du tenant (scope `company_id` verifie).
 * deny-by-default : aucun role = refus (fail-closed, pattern Catalog #6880).
 */
class RetailOnlineSettingsPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailOnlineSettings $settings): bool
    {
        return $settings->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->permissions->canManageSettings($actor);
    }

    public function update(Employee $actor, RetailOnlineSettings $settings): bool
    {
        return $settings->company_id === (string) $actor->company_id
            && $this->permissions->canManageSettings($actor);
    }
}
