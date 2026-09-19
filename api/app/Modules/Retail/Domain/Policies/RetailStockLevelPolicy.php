<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailStockLevel;

/**
 * RBAC des niveaux de stock et mouvements du module Retail (BC-17 RETAIL, #7673).
 *
 * Lecture (niveaux, mouvements, alertes) ouverte aux membres du tenant
 * (scope `company_id` vérifié) ; écriture (enregistrement d'un mouvement,
 * seuls les mouvements modifient les quantités — RetailStockService)
 * réservée au responsable du tenant (sous-rôles `principal`/`rh`).
 * deny-by-default : aucun rôle = refus (fail-closed, pattern Catalog #6880).
 */
class RetailStockLevelPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailStockLevel $level): bool
    {
        return $level->company_id === (string) $actor->company_id;
    }

    /**
     * Enregistrer un mouvement de stock (seule voie d'écriture des quantités).
     */
    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
