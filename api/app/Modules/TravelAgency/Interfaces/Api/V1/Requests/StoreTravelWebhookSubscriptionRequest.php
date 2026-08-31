<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-806 (#6097) — Création/mise à jour d'un abonnement webhook
 * transporteur.
 *
 * Le secret est chiffré au repos et jamais restitué : la réponse expose
 * uniquement son préfixe de hash. L'URL est bornée (500) et les événements
 * doivent appartenir au catalogue `travel.*.v1`.
 */
class StoreTravelWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Vérifié par Policy TravelWebhookSubscriptionPolicy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'carrier_id' => ['required', 'integer', Rule::exists('travel_carriers', 'id')->where('company_id', (string) currentCompany()->id)],
            'url' => ['required', 'url', 'max:500'],
            'secret' => ['required', 'string', 'min:16', 'max:200'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in([
                'travel.booking.pending.v1',
                'travel.booking.confirmed.v1',
                'travel.booking.cancelled.v1',
                'travel.booking.expired.v1',
                'travel.ticket.issued.v1',
                'travel.payment.confirmed.v1',
                'travel.payment.refunded.v1',
            ])],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
