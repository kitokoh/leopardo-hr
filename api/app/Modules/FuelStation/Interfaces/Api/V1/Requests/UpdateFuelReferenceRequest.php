<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une ressource de référence FuelStation (FUEL-011, #5805).
 *
 * Règles partielles (PATCH) : chaque champ validé indépendamment ; les
 * champs absents ne sont pas modifiés. Mêmes enums bornés qu'à la création.
 */
class UpdateFuelReferenceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $resource = (string) $this->route('resource');

        return match ($resource) {
            'stations' => [
                'code' => ['sometimes', 'string', 'max:40'],
                'name' => ['sometimes', 'string', 'max:150'],
                'address' => ['sometimes', 'nullable', 'string', 'max:255'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
                'timezone' => ['sometimes', 'string', 'max:64'],
                'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
                'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
            ],
            'sites' => [
                'station_id' => ['sometimes', 'integer'],
                'code' => ['sometimes', 'string', 'max:40'],
                'name' => ['sometimes', 'string', 'max:150'],
                'address' => ['sometimes', 'nullable', 'string', 'max:255'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'pumps' => [
                'station_id' => ['sometimes', 'integer'],
                'code' => ['sometimes', 'string', 'max:40'],
                'product_types' => ['sometimes', 'array'],
                'product_types.*' => ['string', 'max:40'],
                'status' => ['sometimes', Rule::in(['active', 'inactive', 'retired'])],
            ],
            'tanks' => [
                'station_id' => ['sometimes', 'integer'],
                'code' => ['sometimes', 'string', 'max:40'],
                'product_type' => ['sometimes', 'string', 'max:40'],
                'capacity_minor' => ['sometimes', 'integer', 'min:0'],
                'current_level_minor' => ['sometimes', 'integer', 'min:0'],
                'status' => ['sometimes', Rule::in(['active', 'inactive', 'retired'])],
            ],
            'meters' => [
                'station_id' => ['sometimes', 'integer'],
                'pump_id' => ['sometimes', 'integer'],
                'meter_code' => ['sometimes', 'string', 'max:40'],
                'meter_type' => ['sometimes', Rule::in(['mechanical', 'electronic', 'main_totalizer', 'secondary_totalizer', 'test'])],
                'product_code' => ['sometimes', 'nullable', 'string', 'max:40'],
                'unit_code' => ['sometimes', 'string', 'max:10'],
                'precision_scale' => ['sometimes', 'integer', 'min:0', 'max:6'],
                'rollover_limit' => ['sometimes', 'integer', 'min:0'],
                'installed_at' => ['sometimes', 'nullable', 'date'],
                'status' => ['sometimes', Rule::in(['active', 'retired'])],
            ],
            'products' => [
                'code' => ['sometimes', 'string', 'max:40'],
                'name' => ['sometimes', 'string', 'max:150'],
                'unit_code' => ['sometimes', 'string', 'max:10'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            default => abort(404, 'RESOURCE_UNKNOWN'),
        };
    }
}
