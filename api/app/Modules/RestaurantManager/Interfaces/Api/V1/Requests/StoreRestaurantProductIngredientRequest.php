<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-302 (#6183) — Validation stricte d'un lien recette produit/ingrédient.
 *
 * `ingredient_id` doit référencer un ingrédient du tenant courant (isolation
 * par schéma PostgreSQL) : un ingrédient d'un autre tenant est rejeté en 422.
 * `unit_code` référence `restaurant_units.code` par valeur (sans FK, contrôle
 * applicatif délibéré — le référentiel d'unités du tenant est la source).
 * L'autorisation est tranchée par `RestaurantProductIngredientPolicy::create()`.
 */
class StoreRestaurantProductIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantProductIngredientPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'ingredient_id' => ['required', 'integer', Rule::exists((new RestaurantIngredient)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_code' => ['required', 'string', 'max:20'],
        ];
    }

    private function companyId(): ?string
    {
        $user = $this->user();
        if ($user instanceof Employee && $user->company_id !== null) {
            return $user->company_id;
        }

        return app()->bound('current_company') ? currentCompany()->id : null;
    }
}
