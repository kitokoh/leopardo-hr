<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour des préférences de notification FuelStation (FUEL-019,
 * #5813) — bulk upsert par (event_type, channel[, station_id]).
 */
class UpdateFuelNotificationPreferencesRequest extends FormRequest
{
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
            'preferences' => ['required', 'array', 'min:1', 'max:50'],
            'preferences.*.event_type' => ['required', Rule::in(FuelNotificationPreference::EVENT_TYPES)],
            'preferences.*.channel' => ['required', Rule::in(FuelNotificationPreference::CHANNELS)],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.station_id' => ['nullable', 'integer'],
        ];
    }
}
