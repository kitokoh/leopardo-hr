<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-502 (#6201) — Validation stricte de création d'un bon de commande.
 *
 * Les lignes sont validées dans le tenant courant (`ingredient_id` existant
 * pour la company) ; le total n'est JAMAIS accepté du client — il est
 * recalculé serveur (service RESTO-502).
 */
class StoreRestaurantPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPurchaseOrderPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'branch_id' => ['required', 'integer', Rule::exists('restaurant_branches', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'supplier_id' => ['required', 'integer', Rule::exists('restaurant_suppliers', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'expected_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
