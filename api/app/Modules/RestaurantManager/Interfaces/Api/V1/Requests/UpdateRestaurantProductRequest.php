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
 * RESTO-302 (#6183) — Validation stricte de modification d'un produit du catalogue.
 *
 * L'unicité tenant-scopée du `code` ignore le produit courant
 * (`restaurantProduct` lié par le route model binding) afin de permettre
 * un PUT sans changement de code. L'autorisation est tranchée par
 * `RestaurantProductPolicy::update()`.
 */
class UpdateRestaurantProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantProductPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        $productId = $this->route('restaurantProduct');
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique((new RestaurantProduct)->getTable(), 'code')
                    ->ignore($productId)
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'category_id' => ['sometimes', 'integer', Rule::exists((new RestaurantCategory)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'description_redacted' => ['nullable', 'string'],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
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
