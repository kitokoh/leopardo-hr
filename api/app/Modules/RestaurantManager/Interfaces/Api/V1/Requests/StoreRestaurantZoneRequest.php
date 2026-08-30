<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-301 (#6182) — Validation stricte de création d'une zone de salle.
 *
 * `branch_id` doit référencer une succursale du tenant courant : le scope
 * `BelongsToCompany` de `RestaurantBranch` filtre automatiquement la requête
 * `exists` (une succursale d'un autre tenant est rejetée en 422). Le manager
 * de salle (`manager`) est autorisé à créer des zones (plan de salle).
 */
class StoreRestaurantZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantZonePolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['required', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
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
