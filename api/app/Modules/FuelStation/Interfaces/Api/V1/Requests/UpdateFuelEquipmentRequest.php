<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un équipement (pompe, cuve ou compteur) — FUEL-011 (#5805).
 */
class UpdateFuelEquipmentRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', 'in:active,inactive,retired'],
            'product_types' => ['nullable', 'array'],
            'product_types.*' => ['string', 'max:40'],
            'product_type' => ['nullable', 'string', 'max:40'],
            'capacity_minor' => ['nullable', 'integer', 'gt:0'],
            'current_level_minor' => ['nullable', 'integer', 'min:0'],
            'meter_code' => ['nullable', 'string', 'max:40'],
            'meter_type' => ['nullable', 'in:mechanical,electronic,main_totalizer,secondary_totalizer,test'],
            'unit_code' => ['nullable', 'in:l,gal'],
            'precision_scale' => ['nullable', 'integer', 'min:0', 'max:6'],
            'rollover_limit' => ['nullable', 'integer', 'min:0'],
            'installed_at' => ['nullable', 'date'],
            'retired_at' => ['nullable', 'date'],
        ];
    }
}
