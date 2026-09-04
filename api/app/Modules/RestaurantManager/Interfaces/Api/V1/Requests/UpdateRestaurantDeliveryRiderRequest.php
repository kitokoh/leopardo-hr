<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-605 (#6210) — Mise à jour d'un livreur.
 */
class UpdateRestaurantDeliveryRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryRiderPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle_code' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['prohibited'],
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
