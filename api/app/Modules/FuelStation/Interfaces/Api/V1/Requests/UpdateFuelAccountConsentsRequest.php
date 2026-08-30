<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour des consentements marketing d'un compte (FUEL-016, #5810).
 *
 * Allowlist stricte des canaux (email, sms, whatsapp, call) — tout autre
 * canal est ignoré. Publie `fuel.consent.updated.v1` (outbox FUEL-015).
 */
class UpdateFuelAccountConsentsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'consents' => ['required', 'array'],
            'consents.*' => ['boolean'],
            'consents.email' => ['sometimes', 'boolean'],
            'consents.sms' => ['sometimes', 'boolean'],
            'consents.whatsapp' => ['sometimes', 'boolean'],
            'consents.call' => ['sometimes', 'boolean'],
        ];
    }
}
