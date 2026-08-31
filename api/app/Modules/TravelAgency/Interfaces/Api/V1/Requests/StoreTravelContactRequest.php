<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM (par événement).
 *
 * Champs bornés (anti-abus), consentement explicite requis. La donnée est
 * publiée via l'outbox (`travel.contact.submitted.v1`) — jamais d'écriture
 * directe dans les tables CRM (garde d'isolation #5584).
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
        return true; // Endpoint de captation : auth optionnelle (tenant requis)
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'consent' => ['required', 'boolean', 'accepted'],
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
