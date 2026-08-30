<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-310 (#6040) — Annulation d'un trajet : motif obligatoire.
 *
 * Le motif est conservé dans l'événement outbox (audit traçable) — jamais
 * de PII dans le motif (max 500 caractères).
 */
class CancelTravelTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelTripPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
