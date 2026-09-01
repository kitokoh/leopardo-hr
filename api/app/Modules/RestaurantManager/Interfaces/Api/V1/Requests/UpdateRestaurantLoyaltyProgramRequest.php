<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-606 (#6211) — Validation de mise à jour d'un programme fidélité.
 */
class UpdateRestaurantLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyProgramPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points_per_amount_minor' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_minor' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean', Rule::in([true, false])],
        ];
    }
}
