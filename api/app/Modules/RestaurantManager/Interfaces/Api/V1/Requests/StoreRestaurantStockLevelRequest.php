<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-501 (#6200) — Validation stricte de création d'un niveau de stock.
 *
 * Unicité (tenant, branche, ingrédient) bornée par le `company_id` de
 * l'acteur ; `branch_id` et `ingredient_id` validés dans le tenant courant
 * (règle `exists` tenant-scopée, pattern référentiel RESTO-3xx).
 */
class StoreRestaurantStockLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantStockLevelPolicy::create() tranche
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
            'ingredient_id' => [
                'required',
                'integer',
                Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id)),
                Rule::unique('restaurant_stock_levels', 'ingredient_id')->where(
                    fn (Builder $q) => $q->where('company_id', $actor->company_id)
                        ->where('branch_id', (int) $this->input('branch_id'))
                ),
            ],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'avg_cost_minor' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'alert_threshold' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
