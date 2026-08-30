<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-302 (#6183) — Validation stricte de création d'un produit du catalogue.
 *
 * `code` est unique par tenant : la fermeture `where` borne l'unicité au
 * `company_id` de l'acteur courant. `category_id`, `branch_id` et
 * `tax_rate_id` doivent référencer des entités du tenant courant (isolation
 * par schéma PostgreSQL) — une catégorie d'un autre tenant est rejetée en 422.
 * Les montants sont exprimés en unités mineures entières (minor units).
 * L'autorisation est tranchée par `RestaurantProductPolicy::create()`.
 */
class StoreRestaurantProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantProductPolicy::create() tranche l'autorisation
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
                Rule::unique((new RestaurantProduct)->getTable(), 'code')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists((new RestaurantCategory)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'description_redacted' => ['nullable', 'string'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', Rule::exists((new RestaurantTaxRate)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'is_available' => ['sometimes', 'boolean'],
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
