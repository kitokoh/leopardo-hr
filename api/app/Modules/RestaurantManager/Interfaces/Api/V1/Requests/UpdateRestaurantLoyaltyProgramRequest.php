<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-606 (#6211) — Programme de fidélité (mise à jour).
 */
class UpdateRestaurantLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyProgramPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points_per_amount_minor' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_minor' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
