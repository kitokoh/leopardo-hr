<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-304 (#6185) — Validation stricte de création d'une ligne de menu.
 *
 * Ressource nested : la ligne est créée sous `/restaurant/menus/{restaurantMenu}/items`,
 * le `menu_id` du parent est injecté par le contrôleur (jamais lu depuis le
 * corps de la requête). `product_id` doit référencer un produit du tenant
 * courant — un produit d'un autre tenant est rejeté en 422. L'autorisation
 * est tranchée par `RestaurantMenuItemPolicy::create()`.
 */
class StoreRestaurantMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantMenuItemPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'product_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists((new RestaurantProduct)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_optional' => ['sometimes', 'boolean'],
        ];
    }

    /** Compagnie de l'acteur courant (pattern #3065/#3428 — scope compagnie sur les FK et uniques). */
    private function companyId(): ?string
    {
        $user = $this->user();
        if ($user instanceof Employee && $user->company_id !== null) {
            return $user->company_id;
        }

        return app()->bound('current_company') ? currentCompany()->id : null;
    }
}
