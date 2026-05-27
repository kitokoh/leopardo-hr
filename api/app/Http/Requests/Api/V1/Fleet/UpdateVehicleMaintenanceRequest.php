<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:oil_change,tire,brake,battery,inspection,repair,other',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'mileage_at_service' => 'nullable|integer|min:0',
            'service_date' => 'sometimes|date',
            'next_service_date' => 'nullable|date',
            'next_service_mileage' => 'nullable|integer|min:0',
            'provider' => 'nullable|string|max:200',
        ];
    }
}
