<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-606 (#6211) — Activation d'un client fidélité (opt-in RGPD requis).
 */
class StoreRestaurantLoyaltyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyCustomerPolicy::create() tranche
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
        ];
    }
}
