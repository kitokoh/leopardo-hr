<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FUEL-004 — enregistrement d'un relevé de compteur (spec §13.4).
 * Allowlist stricte : unités, bornes numériques, formats de date.
 */
class StoreMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC : employé authentifié du tenant (vérifié dans le service).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reading_value_minor' => ['required', 'integer', 'min:0', 'max:99999999999999'],
            'reading_unit' => ['sometimes', 'string', 'in:l,ml,gal,ft3'],
            'captured_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'shift_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'device_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9\-_.]{8,191}$/'],
        ];
    }
}
