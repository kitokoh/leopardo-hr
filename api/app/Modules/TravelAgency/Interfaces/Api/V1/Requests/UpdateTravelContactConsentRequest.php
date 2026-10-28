<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-913 (#6421) — Mise à jour des consentements par canal d'un
 * contact voyageur (opt-in/opt-out horodaté, traçabilité RGPD).
 *
 * Chaque canal est optionnel : seuls les canaux fournis sont modifiés.
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
            // Forme historique exposee par le contrat documente (`email_consent`).
            'email_consent' => ['sometimes', 'boolean'],
            'sms_consent' => ['sometimes', 'boolean'],
            'whatsapp_consent' => ['sometimes', 'boolean'],
            // Forme interne (`*_consent_given`), conservee pour les clients
            // deja raccordes a la colonne.
            'email_consent_given' => ['sometimes', 'boolean'],
            'sms_consent_given' => ['sometimes', 'boolean'],
            'whatsapp_consent_given' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Normalise les deux formes de payload vers `*_consent_given`.
     *
     * Sans cette normalisation, un client conforme au contrat documente
     * (`email_consent`) recevait un 200 alors que RIEN n'etait enregistre —
     * un opt-in RGPD silencieusement perdu.
     *
     * @return array<string, mixed>
     */
    public function normalizedConsent(): array
    {
        $normalized = [];

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            $value = $this->input("{$channel}_consent_given", $this->input("{$channel}_consent"));
            if ($value !== null) {
                $normalized["{$channel}_consent_given"] = (bool) $value;
            }
        }

        return $normalized;
    }
}
