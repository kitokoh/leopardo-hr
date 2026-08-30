<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une ressource de référence FuelStation (FUEL-011, #5805).
 *
 * Règles par ressource (routes : stations, sites, pumps, tanks, meters,
 * products). Validation stricte + enums bornés ; company_id injecté côté
 * contrôleur (jamais accepté du client).
 */
class StoreFuelReferenceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $resource = (string) $this->route('resource');

        return match ($resource) {
            'stations' => [
                'code' => ['required', 'string', 'max:40'],
                'name' => ['required', 'string', 'max:150'],
                'address' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'],
                'timezone' => ['nullable', 'string', 'max:64'],
                'currency' => ['nullable', 'string', 'max:10'],
                'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            ],
            'sites' => [
                'station_id' => ['required', 'integer'],
                'code' => ['required', 'string', 'max:40'],
                'name' => ['required', 'string', 'max:150'],
                'address' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ],
            'pumps' => [
                'station_id' => ['required', 'integer'],
                'code' => ['required', 'string', 'max:40'],
                'product_types' => ['nullable', 'array'],
                'product_types.*' => ['string', 'max:40'],
                'status' => ['nullable', Rule::in(['active', 'inactive', 'retired'])],
            ],
            'tanks' => [
                'station_id' => ['required', 'integer'],
                'code' => ['required', 'string', 'max:40'],
                'product_type' => ['required', 'string', 'max:40'],
                'capacity_minor' => ['required', 'integer', 'min:0'],
                'current_level_minor' => ['nullable', 'integer', 'min:0'],
                'status' => ['nullable', Rule::in(['active', 'inactive', 'retired'])],
            ],
            'meters' => [
                'station_id' => ['required', 'integer'],
                'pump_id' => ['required', 'integer'],
                'meter_code' => ['required', 'string', 'max:40'],
                'meter_type' => ['required', Rule::in(['mechanical', 'electronic', 'main_totalizer', 'secondary_totalizer', 'test'])],
                'product_code' => ['nullable', 'string', 'max:40'],
                'unit_code' => ['required', 'string', 'max:10'],
                'precision_scale' => ['nullable', 'integer', 'min:0', 'max:6'],
                'rollover_limit' => ['nullable', 'integer', 'min:0'],
                'installed_at' => ['nullable', 'date'],
                'status' => ['nullable', Rule::in(['active', 'retired'])],
            ],
            'products' => [
                'code' => ['required', 'string', 'max:40'],
                'name' => ['required', 'string', 'max:150'],
                'unit_code' => ['required', 'string', 'max:10'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ],
            default => abort(404, 'RESOURCE_UNKNOWN'),
        };
    }
}
