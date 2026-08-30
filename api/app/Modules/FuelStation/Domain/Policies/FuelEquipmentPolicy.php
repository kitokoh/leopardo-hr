<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelTank;

/**
 * RBAC des équipements FuelStation (pompes, cuves, compteurs) — FUEL-011
 * (#5805).
 *
 * Manager uniquement, deny-by-default : un pompiste ne manipule jamais le
 * référentiel d'équipements (il lit sa pompe via le flux de relevés).
 */
class FuelEquipmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $resource): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $resource): bool
    {
        return $actor->isManager();
    }
}
