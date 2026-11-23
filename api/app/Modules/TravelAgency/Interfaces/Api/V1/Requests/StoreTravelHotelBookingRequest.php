<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * M4 — Réservation d'une chambre sur un intervalle de dates.
 *
 * Le montant n'est jamais accepté du client : il est calculé par l'Action
 * (prix/nuit × nuits × chambres). `idempotency_key` obligatoire.
 */
class StoreTravelHotelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelHotelPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
