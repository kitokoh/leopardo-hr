<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-607 (#6212) — Validation de mise à jour d'une promotion.
 *
 * Mêmes bornes qu'à la création ; `code` unique par tenant hors de la
 * promotion elle-même.
 */
class UpdateRestaurantPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPromotionPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        $routePromotion = $this->route('restaurantPromotion');
        $discountType = $routePromotion instanceof RestaurantPromotion
            ? $routePromotion->discount_type->value
            : (string) $this->input('discount_type', 'percent');

        $rules = [
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique('restaurant_promotions', 'code')
                    ->ignore($this->route('restaurantPromotion')->getKey())
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId)),
            ],
            'title' => ['sometimes', 'string', 'max:150'],
            'discount_type' => ['sometimes', Rule::in(['percent', 'amount'])],
            'value_minor' => ['sometimes', 'integer'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($discountType === 'percent') {
            $rules['value_minor'] = ['sometimes', 'integer', 'between:1,10000'];
        } else {
            $rules['value_minor'] = ['sometimes', 'integer', 'min:1'];
        }

        return $rules;
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
