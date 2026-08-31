<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelTank;

/**
 * RBAC des équipements (pompes, cuves, compteurs) — FUEL-011 (#5805).
 *
 * deny-by-default : CRUD réservé au manager ; lecture pour tout employé du
 * tenant. Cross-tenant → false (404 au contrôleur).
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
        return true;
    }

    public function view(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $equipment): bool
    {
        return $equipment->company_id === (string) $actor->company_id;
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

    public function update(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $equipment): bool
    {
        return $actor->isManager() && $equipment->company_id === (string) $actor->company_id;
    public function update(Employee $actor, FuelPump|FuelTank|FuelMeterRegister $resource): bool
    {
        return $actor->isManager();
    }
}
