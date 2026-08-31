<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;

/**
 * RESTO-501 (#6200) — Policy des niveaux de stock.
 *
 * Lecture : tout employé authentifié du tenant. Écriture des seuils / coût
 * moyen : gérant ou RH (configuration) — le stock lui-même ne se modifie que
 * par mouvements.
 */
class RestaurantStockLevelPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantStockLevel $level): bool
    {
        return $level->company_id === $actor->company_id;
    }

    public function update(Employee $actor, RestaurantStockLevel $level): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $level->company_id === $actor->company_id;
    }
}
