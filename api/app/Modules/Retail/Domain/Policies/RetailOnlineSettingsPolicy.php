<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC des reglages boutique en ligne du module Retail (BC-17, #7808).
 *
 * Lecture ouverte aux membres du tenant (les reglages ne contiennent
 * aucune donnee sensible) ; gestion (opt-in marketplace, slug public,
 * emplacement de fulfillment) reservee au responsable du tenant
 * (sous-roles `principal`/`rh`) — meme portee que la publication produit
 * (RetailProductPolicy). deny-by-default : aucun role = refus
 * (fail-closed, pattern Catalog #6880).
 */
class RetailOnlineSettingsPolicy
{
    public function view(Employee $actor): bool
    {
        return true;
    }

    public function manage(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
