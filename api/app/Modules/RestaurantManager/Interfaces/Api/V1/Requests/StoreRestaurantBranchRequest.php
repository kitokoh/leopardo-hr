<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-301 (#6182) — Validation stricte de création d'une succursale.
 *
 * `code` est unique par tenant : la fermeture `where` borne l'unicité au
 * `company_id` de l'acteur courant (jamais un code d'un autre tenant ne
 * bloque la création). L'autorisation est tranchée par
 * `RestaurantBranchPolicy::create()`.
 */
class StoreRestaurantBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantBranchPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('restaurant_branches', 'code')->where(
                    fn (Builder $query) => $query->where('company_id', $actor->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
