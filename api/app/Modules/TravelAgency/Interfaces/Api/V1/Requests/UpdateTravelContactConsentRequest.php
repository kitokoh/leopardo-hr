<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-913 (#6421) — Mise à jour des consentements par canal d'un
 * contact voyageur (opt-in/opt-out horodaté, traçabilité RGPD).
 *
 * Chaque canal est optionnel : seuls les canaux fournis sont modifiés.
 * Contrat frontend (TRAVEL-914/#6427) : `{email,sms,whatsapp}_consent`.
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
            'email_consent' => ['sometimes', 'boolean'],
            'sms_consent' => ['sometimes', 'boolean'],
            'whatsapp_consent' => ['sometimes', 'boolean'],
        ];
    }
}
