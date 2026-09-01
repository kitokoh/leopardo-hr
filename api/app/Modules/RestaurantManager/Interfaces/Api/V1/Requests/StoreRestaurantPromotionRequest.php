<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-607 (#6212) — Validation stricte de création d'une promotion.
 *
 * Bornes métier (spec §3.5 / D8) :
 * - `code` unique par tenant (fermeture `where` company_id) ;
 * - `discount_type=percent` → `value_minor` borné 1..10 000 (points de base :
 *   1 000 = 10 %, 10 000 = 100 %) — la remise ne peut pas dépasser le
 *   sous-total (BillCalculator la borne de toute façon) ;
 * - `discount_type=amount` → `value_minor` > 0 (minor units) ;
 * - `min_order_minor` ≥ 0, `max_uses` ≥ 1, fenêtre `starts_at < ends_at` ;
 * - `branch_id` tenant-scopé, nullable (= toutes branches).
 */
class StoreRestaurantPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPromotionPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();
        $discountType = $this->input('discount_type', 'percent');

        $rules = [
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('restaurant_promotions', 'code')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'title' => ['required', 'string', 'max:150'],
            'discount_type' => ['required', Rule::in(['percent', 'amount'])],
            'value_minor' => ['required', 'integer'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($discountType === 'percent') {
            $rules['value_minor'] = ['required', 'integer', 'between:1,10000'];
        } else {
            $rules['value_minor'] = ['required', 'integer', 'min:1'];
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
