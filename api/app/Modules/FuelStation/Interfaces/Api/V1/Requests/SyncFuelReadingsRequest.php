<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Synchronisation de relevés (FUEL-014, #5808) — lot idempotent
 * (idempotency_key par relevé), borné à 500 entrées.
 */
class SyncFuelReadingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'array', 'max:500'],
            'readings.*.station_id' => ['required', 'integer'],
            'readings.*.pump_id' => ['required', 'integer'],
            'readings.*.meter_id' => ['required', 'integer'],
            'readings.*.reading_value_minor' => ['required', 'integer', 'min:0'],
            'readings.*.captured_at' => ['nullable', 'date'],
            'readings.*.timezone' => ['nullable', 'string', 'max:64'],
            'readings.*.shift_id' => ['nullable', 'integer'],
            'readings.*.device_reference' => ['nullable', 'string', 'max:120'],
            'readings.*.idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }
}
