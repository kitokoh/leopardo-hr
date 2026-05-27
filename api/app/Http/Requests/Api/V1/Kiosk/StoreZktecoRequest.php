<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class StoreZktecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:100', 'unique:zkteco_devices,serial_number'],
            'name' => ['required', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['nullable', 'in:tcp,udp,cloud_api'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:60'],
            'firmware_version' => ['nullable', 'string', 'max:60'],
        ];
    }
}
