<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;

/**
 * RBAC de l'outbox FuelStation (FUEL-015, #5809).
 *
 * - Manager : audit (liste) et rejeu manuel des événements en dead-letter.
 * - Employé : deny-by-default (l'outbox est un artefact d'exploitation).
 */
class FuelOutboxPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function retry(Employee $actor, FuelOutboxEvent $event): bool
    {
        return $actor->isManager();
    }
}
