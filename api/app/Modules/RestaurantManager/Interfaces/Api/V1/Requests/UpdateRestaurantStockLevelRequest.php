<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-501 (#6200) — Validation stricte de mise à jour d'un niveau de stock.
 *
 * Seuls les SEUILS et le coût moyen sont modifiables ici : la quantité ne
 * change que par les mouvements (StockMovementService). `quantity` est
 * explicitement refusé (invariant stock, spec §4.4).
 */
class UpdateRestaurantStockLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantStockLevelPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'alert_threshold' => ['nullable', 'numeric', 'min:0'],
            'avg_cost_minor' => ['nullable', 'integer', 'min:0'],
            'quantity' => ['prohibited'],
        ];
    }
}
