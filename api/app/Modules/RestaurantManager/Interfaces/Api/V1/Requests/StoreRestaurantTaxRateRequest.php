<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-303 (#6184) — Validation stricte de création d'un taux de TVA.
 *
 * `code` est unique par tenant : la fermeture `where` borne l'unicité au
 * `company_id` de l'acteur courant. `rate_bps` exprime le taux en points
 * de base (ex. 1900 = 19 %) ; `is_default` désigne le taux appliqué par
 * défaut aux nouveaux produits.
 * L'autorisation est tranchée par `RestaurantTaxRatePolicy::create()`.
 */
class StoreRestaurantTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantTaxRatePolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique((new RestaurantTaxRate)->getTable(), 'code')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'label' => ['required', 'string', 'max:80'],
            'rate_bps' => ['required', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
