<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-303 (#6184) — Validation stricte de création d'une unité de mesure.
 *
 * `code` est unique par tenant : la fermeture `where` borne l'unicité au
 * `company_id` de l'acteur courant. Les unités sont référencées par valeur
 * depuis `restaurant_ingredients.unit_code` et
 * `restaurant_product_ingredients.unit_code`.
 * L'autorisation est tranchée par `RestaurantUnitPolicy::create()`.
 */
class StoreRestaurantUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantUnitPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique((new RestaurantUnit)->getTable(), 'code')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'label' => ['required', 'string', 'max:80'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
