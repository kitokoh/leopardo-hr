<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-605 (#6210) — Validation stricte de création d'un livreur.
 */
class StoreRestaurantDeliveryRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryRiderPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'branch_id' => ['required', 'integer', Rule::exists('restaurant_branches', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'employee_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle_code' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
