<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour des préférences de notification FuelStation (FUEL-019,
 * #5813) — bulk upsert par (event_type, channel[, station_id]).
 *
 * L'autorisation (policy managePreferences) est vérifiée ICI, avant la
 * validation : un opérateur non autorisé reçoit 403, jamais 422 (#8136).
 */
class UpdateFuelNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Employee
            && $user->can('managePreferences', new FuelNotificationPreference);
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
