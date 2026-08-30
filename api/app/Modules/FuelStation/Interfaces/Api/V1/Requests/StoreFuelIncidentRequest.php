<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5804 — Signalement d'un incident (FUEL-010).
 */
class StoreFuelIncidentRequest extends FormRequest
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
            'station_id' => ['nullable', 'integer', 'exists:fuel_stations,id'],
            'equipment_type' => ['nullable', 'string', 'in:pump,tank,meter,site,other'],
            'equipment_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['sometimes', 'string', 'in:low,medium,high,critical'],
        ];
    }
}
