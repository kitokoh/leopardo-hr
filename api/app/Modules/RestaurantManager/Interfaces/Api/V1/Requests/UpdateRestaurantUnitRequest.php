<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-303 (#6184) — Validation stricte de modification d'une unité de mesure.
 *
 * L'unicité tenant-scopée du `code` ignore l'unité courante
 * (`restaurantUnit` lié par le route model binding) afin de permettre
 * un PUT sans changement de code. L'autorisation est tranchée par
 * `RestaurantUnitPolicy::update()`.
 */
class UpdateRestaurantUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantUnitPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unitId = $this->route('restaurantUnit');
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique((new RestaurantUnit)->getTable(), 'code')
                    ->ignore($unitId)
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId)),
            ],
            'label' => ['sometimes', 'string', 'max:80'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
