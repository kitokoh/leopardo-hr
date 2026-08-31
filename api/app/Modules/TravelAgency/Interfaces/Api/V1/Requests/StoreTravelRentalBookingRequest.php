<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-320 (#6050) — Création d'une réservation de location.
 *
 * Dates bornées (fin ≥ début), idempotency_key obligatoire. Le montant est
 * calculé côté serveur (Action), jamais accepté du client.
 */
class StoreTravelRentalBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRentalBookingPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:travel_rental_vehicles,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'customer_contact_id' => ['nullable', 'integer'],
            'deposit_amount_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
