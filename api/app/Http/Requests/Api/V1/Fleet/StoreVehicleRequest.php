<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate_number' => 'required|string|max:20',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'type' => 'nullable|in:car,van,truck,motorcycle,bus',
            'vin' => 'nullable|string|max:17',
            'fuel_type' => 'nullable|in:diesel,gasoline,electric,hybrid,lpg',
            'status' => 'nullable|in:active,maintenance,decommissioned',
            'mileage' => 'nullable|integer|min:0',
            'insurance_expiry' => 'nullable|date',
            'technical_control_expiry' => 'nullable|date',
            'traccar_unique_id' => 'nullable|string|max:50',
            'assigned_driver_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ];
    }
}
