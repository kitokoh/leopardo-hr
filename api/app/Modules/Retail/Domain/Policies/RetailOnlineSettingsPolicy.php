<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;

/**
 * RBAC des reglages de la boutique en ligne Leopardo Marche
 * (BC-17 RETAIL, #7807).
 *
 * Gestion (activation marketplace, create-or-update) reservee au
 * responsable du tenant (sous-roles `principal`/`rh`) ; lecture ouverte
 * aux membres du tenant (scope `company_id` verifie) — calque sur
 * RetailLocationPolicy (#7673). deny-by-default : aucun role = refus
 * (fail-closed, pattern Catalog #6880).
 */
class RetailOnlineSettingsPolicy
{
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
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RetailOnlineSettings $settings): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $settings->company_id === (string) $actor->company_id;
    }
}
