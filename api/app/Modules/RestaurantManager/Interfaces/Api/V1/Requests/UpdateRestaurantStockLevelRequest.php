<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-501 (#6200) — Validation stricte de mise à jour d'un niveau de stock.
 *
 * Seuls les attributs de gestion (seuils, coût moyen) sont modifiables en
 * place : la quantité ne se modifie que par mouvement (service de stock),
 * garantissant la traçabilité du journal.
 */
class UpdateRestaurantStockLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantStockLevelPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'avg_cost_minor' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'alert_threshold' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['prohibited', Rule::exists('restaurant_branches', 'id')->where(fn ($q) => $q->where('company_id', $actor->company_id))],
            'ingredient_id' => ['prohibited', Rule::exists('restaurant_ingredients', 'id')->where(fn ($q) => $q->where('company_id', $actor->company_id))],
        ];
    }
}
