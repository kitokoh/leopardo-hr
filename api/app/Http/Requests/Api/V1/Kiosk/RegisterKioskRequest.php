<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class RegisterKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'biometric_mode' => ['nullable', 'in:fingerprint,face,mixed'],
            'trusted_device_label' => ['nullable', 'string', 'max:120'],
        ];
    }
}
