<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-315 (#6045) / TRAVEL-808 (#6098) — Remboursement d'une
 * réservation.
 *
 * `passenger_ids` optionnel : si fourni, remboursement PARTIEL limité aux
 * passagers listés (aucun → remboursement intégral). La pénalité est
 * calculée serveur via la politique d'annulation (TRAVEL-813) — jamais
 * acceptée du client.
 */
class RefundTravelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelBookingPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'passenger_ids' => ['nullable', 'array', 'min:1', 'max:20'],
            'passenger_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
