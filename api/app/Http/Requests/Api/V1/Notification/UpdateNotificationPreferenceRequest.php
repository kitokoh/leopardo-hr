<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'nullable', 'in:fr,ar,en,tr'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['boolean'],
            'quiet_hours' => ['sometimes', 'array'],
            'quiet_hours.enabled' => ['sometimes', 'boolean'],
            'quiet_hours.start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours.end' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }
}
