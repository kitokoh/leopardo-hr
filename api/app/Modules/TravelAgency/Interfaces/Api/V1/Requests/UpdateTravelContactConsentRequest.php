<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-913 — Mise à jour du consentement d'un contact voyageur
 * (opt-in / opt-out horodaté, traçabilité RGPD §8.5).
 */
class UpdateTravelContactConsentRequest extends FormRequest
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
            'channel' => ['required', 'string', 'in:email,sms,whatsapp'],
            'given' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel.required' => 'Le canal de consentement est obligatoire.',
            'channel.in' => 'Le canal doit être email, sms ou whatsapp.',
            'given.required' => 'Le consentement (oui/non) est obligatoire.',
            'given.boolean' => 'Le consentement doit être un booléen.',
        ];
    }
}
