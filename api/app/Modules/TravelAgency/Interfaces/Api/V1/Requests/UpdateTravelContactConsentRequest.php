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
        // #8396 — le contrat documenté (TRAVEL-913) et le client envoient
        // `{email_consent, sms_consent, whatsapp_consent}` ; la validation ne
        // connaissait que la forme interne `*_consent_given`. Le corps passait
        // donc sans être validé, `validated()` renvoyait `null` partout et
        // aucun consentement n'était enregistré — en 200, silencieusement.
        // Les deux formes sont acceptées ; la forme courte est la canonique.
        $rules = [];

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            $rules["{$channel}_consent"] = ['sometimes', 'boolean'];
            $rules["{$channel}_consent_given"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
