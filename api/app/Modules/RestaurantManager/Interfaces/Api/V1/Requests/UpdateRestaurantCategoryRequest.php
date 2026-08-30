<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-302 (#6183) — Validation stricte de modification d'une catégorie de produits.
 *
 * Champs requis à la création passés en `sometimes` : un PUT partiel reste
 * accepté. L'autorisation est tranchée par `RestaurantCategoryPolicy::update()`.
 */
class UpdateRestaurantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantCategoryPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'name' => ['sometimes', 'string', 'max:120'],
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
