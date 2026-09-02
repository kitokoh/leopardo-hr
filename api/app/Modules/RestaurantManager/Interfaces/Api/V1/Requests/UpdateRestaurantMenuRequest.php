<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-304 (#6185) — Validation stricte de modification d'un menu restaurant.
 *
 * L'unicité tenant-scopée du `code` ignore le menu courant
 * (`restaurantMenu` lié par le route model binding) afin de permettre un
 * `PUT` sans changement de code. L'autorisation est tranchée par
 * `RestaurantMenuPolicy::update()`.
 */
class UpdateRestaurantMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantMenuPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $menuId = $this->route('restaurantMenu');
        $companyId = $this->companyId();

        return [
            'code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique('restaurant_menus', 'code')
                    ->ignore($menuId)
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'branch_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
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
