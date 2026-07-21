<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCompanyModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'forced_mode'             => ['nullable', 'string', 'in:gps_auto,qr,manual,mixed'],
            'punch_photo_mode'        => ['nullable', 'string', 'in:kiosk,photo_required'],
            'gps_enabled'             => ['boolean'],
            'latitude'                => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'               => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters'           => ['nullable', 'integer', 'min:10', 'max:5000'],
            'allow_employee_override' => ['boolean'],
        ];
    }
}
