<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-301 (#6182) — Validation stricte de modification d'une succursale.
 *
 * L'unicité tenant-scopée du `code` ignore la succursale courante
 * (`restaurantBranch` lié par le route model binding) afin de permettre
 * un `PUT` sans changement de code. L'autorisation est tranchée par
 * `RestaurantBranchPolicy::update()`.
 */
class UpdateRestaurantBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantBranchPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();
        $branchId = $this->route('restaurantBranch');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique('restaurant_branches', 'code')
                    ->ignore($branchId)
                    ->where(fn (Builder $query) => $query->where('company_id', $actor->company_id)),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
