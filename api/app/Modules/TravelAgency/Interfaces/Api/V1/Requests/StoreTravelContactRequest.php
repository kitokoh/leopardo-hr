<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → événement lead CRM.
 *
 * Validation stricte : email requis, message borné, consentement de
 * contact explicite obligatoire (RGPD). Aucune écriture CRM directe —
 * un événement `travel.contact.submitted.v1` est publié via l'outbox.
 */
class StoreTravelContactRequest extends FormRequest
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
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'consent_email' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent_email.required' => 'Le consentement de contact est obligatoire.',
            'consent_email.accepted' => 'Le consentement de contact doit être accepté.',
            'message.max' => 'Le message ne doit pas dépasser 2000 caractères.',
        ];
    }
}
