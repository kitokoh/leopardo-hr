<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-320 (#6050) — Annulation d'une réservation de location : motif
 * obligatoire (audit).
 */
class CancelTravelRentalBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRentalBookingPolicy::update() tranche l'autorisation
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
