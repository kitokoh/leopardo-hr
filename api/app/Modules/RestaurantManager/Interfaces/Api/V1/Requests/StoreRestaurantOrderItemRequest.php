<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-403 (#6190) — Validation stricte d'ajout d'un article de commande.
 *
 * `product_id` doit appartenir au tenant (existence + disponibilité + branche
 * tranchées par AddOrderItemAction) ; `quantity` est un décimal strictement
 * positif ; `menu_id` optionnel et tenant-scopé. Aucun prix n'est accepté du
 * client (le prix vient du référentiel serveur).
 */
class StoreRestaurantOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantOrderPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantProduct)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'menu_id' => [
                'nullable',
                'integer',
                Rule::exists((new RestaurantMenu)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
        ];
    }
}
