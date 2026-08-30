<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-503 (#6202) — Validation stricte d'une réception de marchandises.
 *
 * La référence est optionnelle (auto-générée `RCV-*`) ; si fournie, elle
 * doit être unique par tenant (idempotence du rejeu client). Les lignes
 * (ingrédients, quantités, prix) sont validées dans le tenant courant.
 */
class StoreRestaurantReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReceivingPolicy::create() tranche
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
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('restaurant_purchase_orders', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'supplier_id' => ['nullable', 'integer', Rule::exists('restaurant_suppliers', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'reference' => ['nullable', 'string', 'max:40', Rule::unique('restaurant_receivings', 'reference')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
