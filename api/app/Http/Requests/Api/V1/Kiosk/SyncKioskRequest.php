<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class SyncKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array'],
            'events.*.identifier' => ['required', 'string', 'max:150'],
            'events.*.action' => ['nullable', 'in:check_in,check_out'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.external_event_id' => ['nullable', 'string', 'max:100'],
            'events.*.biometric_type' => ['nullable', 'in:fingerprint,face,mixed'],
        ];
    }
}
