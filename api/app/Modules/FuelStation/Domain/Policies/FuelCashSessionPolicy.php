<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;

/**
 * RBAC des sessions de caisse FuelStation (FUEL-007, #5801).
 *
 * - Tout employé authentifié (pompiste) : ouvrir une session, ajouter des
 *   mouvements et clôturer SES propres sessions.
 * - Manager : voir toutes les sessions du tenant et approuver les clôtures
 *   (verrouillage des écarts).
 */
class FuelCashSessionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelCashSession $session): bool
    {
        return $actor->isManager() || $session->opened_by === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function addMovement(Employee $actor, FuelCashSession $session): bool
    {
        return $session->opened_by === $actor->id;
    }

    public function close(Employee $actor, FuelCashSession $session): bool
    {
        return $session->opened_by === $actor->id;
    }

    public function approve(Employee $actor, FuelCashSession $session): bool
    {
        return $actor->isManager();
    }
}
