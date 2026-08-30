<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC du référentiel FuelStation (FUEL-011, issue #5805).
 *
 * Deny-by-default : tout le CRUD des référentiels (stations, sites, pompes,
 * cuves, compteurs, produits) est réservé au manager. Un pompiste ne peut
 * ni lire ni écrire ces référentiels via l'API d'administration — il
 * consomme uniquement ses endpoints self-service (/fuel-station/me/*) et
 * les lectures métier autorisées (ventes, relevés, incidents signalés).
 */
class FuelReferencePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function delete(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewReports(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
