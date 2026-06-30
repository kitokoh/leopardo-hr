<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferred_mode'   => ['required', 'string', 'in:gps_auto,qr,manual'],
            'gps_consent_given' => ['nullable', 'boolean'],
            'revoke_consent'   => ['nullable', 'boolean'],
        ];
    }
}
