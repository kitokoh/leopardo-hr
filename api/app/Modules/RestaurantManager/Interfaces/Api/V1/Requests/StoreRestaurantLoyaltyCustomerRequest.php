<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-606 (#6211) — Activation d'un client fidélité (opt-in RGPD requis).
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
        return true; // RestaurantLoyaltyCustomerPolicy::create() tranche
        return true; // RestaurantLoyaltyCustomerPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'customer_contact_id' => ['required', 'integer', Rule::unique('restaurant_loyalty_customers', 'customer_contact_id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'opt_in' => ['required', 'boolean'],
        return [
            'customer_contact_id' => ['required', 'integer'],
            'tier_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
