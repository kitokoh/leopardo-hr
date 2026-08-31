<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-806 (#6097) — Mise à jour d'un abonnement webhook.
 *
 * Le secret n'est jamais modifiable via l'API (rotation = suppression +
 * recréation) ; `has_secret` seul est exposé.
 */
class UpdateTravelWebhookSubscriptionRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'url' => ['sometimes', 'string', 'url', 'max:500'],
            'carrier_id' => ['nullable', 'integer'],
            'events' => ['sometimes', 'array', 'min:1', 'max:10'],
            'events.*' => ['required', 'string', Rule::in(TravelWebhookSubscription::supportedEvents())],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
