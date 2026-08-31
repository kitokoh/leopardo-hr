<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-501 (#6200) — Validation stricte de création d'un mouvement de stock.
 *
 * Le delta est signé (négatif pour sortie : vente, gaspillage, ajustement) ;
 * la raison est bornée à l'enum `StockMovementReason` ; les références
 * polymorphes (bon de commande, réception, inventaire) restent optionnelles.
 */
class StoreRestaurantInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantInventoryMovementPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        $reasons = array_map(fn (StockMovementReason $reason) => $reason->value, StockMovementReason::cases());

        return [
            'branch_id' => ['required', 'integer', Rule::exists('restaurant_branches', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'ingredient_id' => ['required', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'quantity_delta' => ['required', 'numeric', 'not_in:0'],
            'reason_code' => ['required', 'string', Rule::in($reasons)],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'integer'],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
