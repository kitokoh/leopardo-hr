<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-303 (#6184) — Validation stricte de création d'un ingrédient.
 *
 * `code` est unique par (tenant, branche) — la fermeture `where` borne
 * l'unicité au `company_id` de l'acteur courant. `branch_id` null = matière
 * commune à toutes les branches. `unit_code` référence `restaurant_units.code`
 * par valeur ; `avg_cost_minor` sert au coût théorique des recettes.
 * L'autorisation est tranchée par `RestaurantIngredientPolicy::create()`.
 */
class StoreRestaurantIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantIngredientPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique((new RestaurantIngredient)->getTable(), 'code')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'unit_code' => ['required', 'string', 'max:20'],
            'avg_cost_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
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
