<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelTank;

/**
 * Cas d'usage : mise à jour d'un équipement (pompe, cuve ou compteur) du tenant.
 */
class UpdateEquipmentAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés par la Request
     */
    public function execute(FuelPump|FuelTank|FuelMeterRegister $item, array $data): FuelPump|FuelTank|FuelMeterRegister
    {
        $item->update($data);

        return $item->refresh() ?? $item;
    }
}
