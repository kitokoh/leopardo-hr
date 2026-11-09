<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des niveaux de stock et mouvements du module Retail (BC-17 RETAIL, #7673).
 *
 * #9207 — permission `retail.stock` reellement appliquee : enregistrer un
 * mouvement (reception, inventaire, casse) est un geste de VENDEUR (direction,
 * ou niveau `operate` sur au moins une boutique assignee). Lecture (niveaux,
 * mouvements, alertes) ouverte aux membres du tenant (scope `company_id`
 * verifie). deny-by-default : aucun role = refus (fail-closed).
 */
class RetailStockLevelPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailStockLevel $level): bool
    {
        return $level->company_id === (string) $actor->company_id;
    }

    /**
     * Enregistrer un mouvement de stock (seule voie d'ecriture des quantites).
     */
    public function create(Employee $actor): bool
    {
        return $this->permissions->canOperateStock($actor);
    }
}
