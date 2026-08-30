<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-605 (#6210) — Validation stricte de mise à jour d'un livreur.
 */
class UpdateRestaurantDeliveryRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryRiderPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'branch_id' => ['sometimes', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'employee_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle_code' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
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
