<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-504 (#6203) — Validation stricte de création d'un inventaire physique.
 */
class StoreRestaurantInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantInventoryCountPolicy::create() tranche l'autorisation
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
            'counted_at' => ['nullable', 'date'],
        ];
    }
}
