<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-302 (#6183) — Validation stricte de création d'une catégorie de produits.
 *
 * `branch_id` null signifie que la catégorie s'applique à toutes les branches
 * du tenant ; une branche d'un autre tenant est rejetée en 422 (isolation par
 * schéma PostgreSQL : la requête `exists` ne voit que le schéma du tenant).
 * L'autorisation est tranchée par `RestaurantCategoryPolicy::create()`.
 */
class StoreRestaurantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantCategoryPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
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
