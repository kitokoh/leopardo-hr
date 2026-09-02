<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-301 (#6182) — Validation stricte de modification d'une table de salle.
 *
 * `branch_id` (obligatoire) et `zone_id` (optionnel) doivent référencer des
 * entités du tenant courant (scopes `BelongsToCompany` sur les règles
 * `exists`). L'autorisation est tranchée par `RestaurantTablePolicy::update()`.
 */
class UpdateRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantTablePolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'label' => ['sometimes', 'string', 'max:80'],
            'branch_id' => ['sometimes', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'zone_id' => ['nullable', 'integer', Rule::exists((new RestaurantZone)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'min_covers' => ['nullable', 'integer'],
            'is_mergeable' => ['sometimes', 'boolean'],
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
