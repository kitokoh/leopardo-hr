<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-806 (#6097) — Création d'un abonnement webhook transporteur.
 *
 * Le secret est optionnel : s'il est absent, un secret aléatoire est généré
 * et renvoyé UNE fois à la création (jamais ré-exposé ensuite).
 */
class StoreTravelWebhookSubscriptionRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'url' => ['required', 'string', 'url', 'max:500'],
            'carrier_id' => ['nullable', 'integer'],
            'events' => ['required', 'array', 'min:1', 'max:10'],
            'events.*' => ['required', 'string', Rule::in(TravelWebhookSubscription::supportedEvents())],
            'secret' => ['nullable', 'string', 'min:16', 'max:200'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.url' => 'L\'URL de livraison doit être une URL valide (https).',
            'events.required' => 'Sélectionnez au moins un événement à livrer.',
            'events.*.in' => 'Événement webhook inconnu.',
        ];
    }
}
