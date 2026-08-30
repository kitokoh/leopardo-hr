<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-314 (#6044) / TRAVEL-315 (#6045) — Annulation / remboursement :
 * motif obligatoire (audit).
 */
class CancelTravelBookingRequest extends FormRequest
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
        ];
    }
}
