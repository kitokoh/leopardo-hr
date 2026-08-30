<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-914 (#6427) — Mise à jour du consentement par canal (RGPD).
 *
 * Un canal est soit opt-in horodaté (`consent: true` → `{channel}_consent_at`
 * = now()), soit opt-out (`consent: false` → `{channel}_consent_at` = null).
 * Défaut : aucun envoi sans consentement explicite (registre #6067).
 */
class UpdateTravelCustomerContactConsentRequest extends FormRequest
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
            'channel' => ['required', 'string', 'in:email,sms,whatsapp'],
            'consent' => ['required', 'boolean'],
        ];
    }
}
