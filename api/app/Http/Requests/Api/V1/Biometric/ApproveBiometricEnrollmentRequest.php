<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Biometric;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBiometricEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manager_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
