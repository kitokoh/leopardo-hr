<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-501 (#6200) — Validation stricte d'un mouvement de stock manuel.
 *
 * Raisons acceptées : adjustment|waste|transfer (les raisons sale/receiving/
 * count sont générées par les flux métier). `quantity_delta` non nul ;
 * ingrédient et branche tenant-scopés. L'application du mouvement (verrou,
 * jamais négatif) est tranchée par StockMovementService.
 */
class StoreRestaurantInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantInventoryMovementPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'ingredient_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantIngredient)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'quantity_delta' => ['required', 'numeric', 'not_in:0'],
            'reason_code' => ['required', 'string', Rule::in(['adjustment', 'waste', 'transfer'])],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
