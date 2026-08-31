<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-606 (#6211) — Validation d'un échange de points fidélité.
 *
 * `points` strictement positif ; le solde « jamais négatif » est tranché par
 * RedeemLoyaltyPointsAction (422 si l'échange dépasse le solde).
 */
class RedeemRestaurantLoyaltyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyCustomerPolicy::redeem() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1'],
        ];
    }
}
