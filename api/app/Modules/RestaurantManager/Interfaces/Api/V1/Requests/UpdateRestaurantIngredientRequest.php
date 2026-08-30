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
 * RESTO-303 (#6184) — Validation stricte de modification d'un ingrédient.
 *
 * L'unicité tenant-scopée du `code` ignore l'ingrédient courant
 * (`restaurantIngredient` lié par le route model binding) afin de permettre
 * un PUT sans changement de code. L'autorisation est tranchée par
 * `RestaurantIngredientPolicy::update()`.
 */
class UpdateRestaurantIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantIngredientPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        $ingredientId = $this->route('restaurantIngredient');
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique((new RestaurantIngredient)->getTable(), 'code')
                    ->ignore($ingredientId)
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'unit_code' => ['sometimes', 'string', 'max:20'],
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
