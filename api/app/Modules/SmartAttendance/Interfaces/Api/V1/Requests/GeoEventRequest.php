<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeoEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth via middleware sanctum
    }

    public function rules(): array
    {
        return [
            'event_type'       => ['required', 'string', 'in:zone_enter,zone_exit'],
            'latitude'         => ['required', 'numeric', 'between:-90,90'],
            'longitude'        => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'device_timestamp' => ['nullable', 'date'],
            'metadata'         => ['nullable', 'array'],
        ];
    }
}
