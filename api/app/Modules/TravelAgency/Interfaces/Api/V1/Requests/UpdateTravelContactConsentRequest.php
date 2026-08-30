<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
<<<<<<< HEAD
 * TRAVEL-913 (#6421) — Mise à jour des consentements par canal d'un
 * contact voyageur (opt-in/opt-out horodaté, traçabilité RGPD).
 *
 * Chaque canal est optionnel : seuls les canaux fournis sont modifiés.
=======
 * TRAVEL-912 (#6417) — Mise à jour des consentements par canal d'un
 * contact voyageur (gestion). Au moins un canal requis ; opt-out horodaté.
>>>>>>> origin/feat/travel-101-202-foundations
 */
class UpdateTravelContactConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rôles gestion tranchés au controller (hasManagerRole)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
<<<<<<< HEAD
            'email_consent_given' => ['sometimes', 'boolean'],
            'sms_consent_given' => ['sometimes', 'boolean'],
            'whatsapp_consent_given' => ['sometimes', 'boolean'],
=======
            'email_consent' => ['nullable', 'boolean'],
            'sms_consent' => ['nullable', 'boolean'],
            'whatsapp_consent' => ['nullable', 'boolean'],
>>>>>>> origin/feat/travel-101-202-foundations
        ];
    }
}
