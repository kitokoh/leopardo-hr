<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-502 (#6201) — Validation stricte d'une ligne de bon de commande.
 *
 * Ingrédient tenant-scopé ; quantité strictement positive ; prix unitaire
 * en minor units (le total de ligne est calculé serveur).
 */
class StoreRestaurantPurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPurchaseOrderPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        return [
            'ingredient_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantIngredient)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'unit_price_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
        ];
    }
}
