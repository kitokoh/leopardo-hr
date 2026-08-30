<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-606 (#6211) — Validation de l'opt-in fidélité d'un client.
 *
 * `customer_contact_id` = identifiant du contact CRM ; le compte fidélité est
 * unique par (tenant, contact) — l'opt-in est explicite (RGPD) : sans ce
 * compte, aucun point n'est crédité (CreditLoyaltyPointsAction).
 */
class StoreRestaurantLoyaltyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyCustomerPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_contact_id' => ['required', 'integer'],
            'tier_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
