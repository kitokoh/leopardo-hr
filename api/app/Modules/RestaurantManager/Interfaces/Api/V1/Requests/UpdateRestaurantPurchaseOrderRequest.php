<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-502 (#6201) — Mise à jour d'un bon de commande (brouillon uniquement).
 *
 * Les lignes ajoutées sont validées dans le tenant courant ; le statut et la
 * référence sont immutables (le changement de statut passe par `send`).
 */
class UpdateRestaurantPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPurchaseOrderPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'expected_at' => ['nullable', 'date'],
            'status' => ['prohibited'],
            'reference' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'supplier_id' => ['prohibited'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
