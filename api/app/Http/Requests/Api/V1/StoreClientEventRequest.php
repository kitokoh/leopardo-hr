<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientEventRequest extends FormRequest
{
    public const ALLOWED_EVENTS = [
        'login_success',
        'dashboard_loaded',
        'feature_blocked',
        'demo_user_selected',
        'kiosk_status',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::in(self::ALLOWED_EVENTS)],
            'surface' => ['nullable', 'string', Rule::in(['web', 'admin', 'mobile', 'kiosk'])],
            'session_id' => ['nullable', 'string', 'max:120'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'occurred_at' => ['nullable', 'date'],
            'properties' => ['nullable', 'array', 'max:30'],
            'properties.*' => ['nullable'],
        ];
    }
}
