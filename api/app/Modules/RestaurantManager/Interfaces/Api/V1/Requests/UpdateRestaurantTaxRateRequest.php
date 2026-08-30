<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-303 (#6184) — Validation stricte de modification d'un taux de TVA.
 *
 * L'unicité tenant-scopée du `code` ignore le taux courant
 * (`restaurantTaxRate` lié par le route model binding) afin de permettre
 * un PUT sans changement de code. L'autorisation est tranchée par
 * `RestaurantTaxRatePolicy::update()`.
 */
class UpdateRestaurantTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantTaxRatePolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $taxRateId = $this->route('restaurantTaxRate');
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique((new RestaurantTaxRate)->getTable(), 'code')
                    ->ignore($taxRateId)
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId)),
            ],
            'label' => ['sometimes', 'string', 'max:80'],
            'rate_bps' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
