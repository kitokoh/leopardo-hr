<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-304 (#6185) — Validation stricte de modification d'une ligne de menu.
 *
 * Mêmes contraintes qu'à la création, en `sometimes` pour permettre un
 * `PUT` partiel. L'autorisation est tranchée par
 * `RestaurantMenuItemPolicy::update()`.
 */
class UpdateRestaurantMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantMenuItemPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'product_id' => [
                'sometimes',
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
