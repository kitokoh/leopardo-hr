<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Biometric;

use Illuminate\Foundation\Http\FormRequest;

class StoreBiometricEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_face_enabled' => ['nullable', 'boolean'],
            'requested_fingerprint_enabled' => ['nullable', 'boolean'],
            'requested_fingerprint_reference_path' => ['nullable', 'string', 'max:255'],
            'requested_fingerprint_device_id' => ['nullable', 'string', 'max:100'],
            'employee_note' => ['nullable', 'string', 'max:1000'],
            'face_image' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
