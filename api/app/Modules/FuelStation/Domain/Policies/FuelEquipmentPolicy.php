<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelTank;

/**
 * RBAC des équipements (pompes, cuves, compteurs) — FUEL-004/#5798.
 * Lecture ouverte au tenant (l'écran pompiste appelle les équipements),
 * écriture manager — croisée avec l'accès ressource-scopé (#7600).
 */
class FuelEquipmentPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $equipment): bool
    {
        return $equipment->company_id === (string) $actor->company_id
            && $this->canViewScopedResource($actor, 'fuel_station', $equipment->station_id ?? null);
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function update(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $equipment): bool
    {
        return $actor->isManager()
            && $equipment->company_id === (string) $actor->company_id
            && $this->canManageScopedResource($actor, 'fuel_station', $equipment->station_id ?? null, true);
    }
}
