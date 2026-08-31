<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * RBAC du reporting opérationnel (FUEL-017, #5811). deny-by-default :
 * rapports et exports réservés aux managers.
use App\Modules\FuelStation\Domain\Models\FuelReportExport;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;

/**
 * RBAC du reporting FuelStation (FUEL-017, #5811).
 *
 * - Manager : consultation des read models, lancement et téléchargement
 *   des exports.
 * - Employé : deny-by-default (le reporting opérationnel est un outil de
 *   pilotage manager).
 */
class FuelReportPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function export(Employee $actor): bool
    public function view(Employee $actor, FuelReportSnapshot|FuelReportExport $resource): bool
    {
        return $actor->isManager();
    }

    public function createExport(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function download(Employee $actor, FuelReportExport $export): bool
    {
        return $actor->isManager();
    }
}
