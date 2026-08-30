<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-811 (#6101) — Échange de points contre une récompense (débit
 * idempotent par réservation).
 */
class RedeemLoyaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Périmètre borné par le contrôleur (tenant).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_identifier' => ['required', 'string', 'max:255'],
            'reward_id' => ['required', 'integer', 'exists:travel_loyalty_rewards,id'],
            'booking_id' => ['required', 'integer', 'exists:travel_bookings,id'],
        ];
    }
}
