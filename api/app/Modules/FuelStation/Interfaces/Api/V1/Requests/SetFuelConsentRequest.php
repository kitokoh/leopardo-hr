<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement de consentement marketing (FUEL-016, #5810) — opt-in/opt-out
 * RGPD explicite, versionné (outbox fuel.customer.consent.updated.v1).
 */
class SetFuelConsentRequest extends FormRequest
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
            'marketing_consent' => ['required', 'boolean'],
        ];
    }
}
