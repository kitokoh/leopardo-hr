<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class ConnectCalendarSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'in:google,outlook,caldav'],
            'access_token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'calendar_id' => ['nullable', 'string', 'max:255'],
            'expires_in' => ['nullable', 'integer'],
            'sync_leaves' => ['nullable', 'boolean'],
            'sync_training' => ['nullable', 'boolean'],
        ];
    }
}
