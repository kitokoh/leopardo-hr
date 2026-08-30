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
 */
class StoreTravelContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Endpoint de captation : auth optionnelle (tenant requis)
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
        ];
    }
}
