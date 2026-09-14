<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelTank;

/**
 * Cas d'usage : création d'un équipement (pompe, cuve ou compteur) rattaché
 * à une station du tenant. La station est déjà vérifiée dans le tenant par
 * l'appelant (fail-closed 404/422) ; les données sont les champs validés.
 */
class CreateStationEquipmentAction
{
    /**
     * @param  string  $companyId  UUID de la société (`companies.id`) — jamais un entier :
     *                             `fuel_pumps/tanks/meter_registers.company_id` est de type `uuid`.
     *                             Un `(int)` de l'UUID produisait « invalid input syntax for type uuid: "1" ».
     * @param  array<string, mixed>  $data  champs validés par la Request
     */
    public function execute(string $companyId, int $stationId, string $kind, array $data): FuelPump|FuelTank|FuelMeterRegister
    {
        return match ($kind) {
            'tank' => FuelTank::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                ...$data,
            ]),
            'meter' => FuelMeterRegister::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                ...$data,
            ]),
            default => FuelPump::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                ...$data,
            ]),
        };
    }
}
