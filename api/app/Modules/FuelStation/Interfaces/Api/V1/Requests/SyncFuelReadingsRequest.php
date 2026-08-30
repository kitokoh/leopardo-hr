<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Synchronisation offline de relevés (FUEL-014, issue #5808).
 *
 * Accepte jusqu'à 100 relevés capturés hors-ligne. Chaque item porte :
 * - `idempotency_key` (généré côté client, unique) → rejeu sans doublon ;
 * - `captured_at` (horloge APPAREIL) — distinct de l'horodatage de réception
 *   serveur (`created_at`), conservé tel quel pour le calcul des intervalles ;
 * - références station/pompe/compteur dans le tenant (404 si hors tenant).
 */
class SyncFuelReadingsRequest extends FormRequest
{
    private const MAX_ITEMS = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'readings.*.station_id' => ['required', 'integer'],
            'readings.*.pump_id' => ['required', 'integer'],
            'readings.*.meter_id' => ['required', 'integer'],
            'readings.*.reading_value_minor' => ['required', 'integer', 'min:0', 'max:99999999999999'],
            'readings.*.reading_unit' => ['sometimes', 'string', 'in:l,ml,gal,ft3'],
            'readings.*.captured_at' => ['sometimes', 'nullable', 'date'],
            'readings.*.timezone' => ['sometimes', 'string', 'max:64'],
            'readings.*.shift_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'readings.*.device_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'readings.*.idempotency_key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9\-_.]{8,191}$/'],
        ];
    }
}
