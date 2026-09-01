<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
