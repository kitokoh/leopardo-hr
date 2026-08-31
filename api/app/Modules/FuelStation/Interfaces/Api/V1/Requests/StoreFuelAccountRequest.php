<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / mise à jour d'un compte professionnel (FUEL-016, #5810).
 *
 * `code` unique par tenant (upsert idempotent) ; `contact` est chiffré au
 * stockage (RGPD) ; `consents` allowlist par canal marketing.
 */
class StoreFuelAccountRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'station_id' => ['nullable', 'integer', 'exists:fuel_stations,id'],
            'code' => ['required', 'string', 'max:60'],
            'name' => ['nullable', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:60'],
            'contact' => ['nullable', 'string', 'max:500'],
            'consents' => ['nullable', 'array'],
            'consents.*' => ['boolean'],
            'status' => ['nullable', Rule::in(FuelProfessionalAccount::STATUSES)],
            'external_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}
