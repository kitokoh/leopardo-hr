<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-401 (#6188) — Validation stricte d'ouverture d'une session de caisse.
 *
 * `branch_id` doit référencer une branche du tenant courant (règle `exists`
 * scopée BelongsToCompany) ; le fonds d'ouverture est un entier minor units
 * ≥ 0. L'autorisation est tranchée par `RestaurantPosSessionPolicy::create()`.
 */
class StoreRestaurantPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPosSessionPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'opening_cash_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
        ];
    }
}
