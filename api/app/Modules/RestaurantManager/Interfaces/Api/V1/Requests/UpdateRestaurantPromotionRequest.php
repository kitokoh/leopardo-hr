<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-607 (#6212) — Mise à jour d'une promotion.
 */
class UpdateRestaurantPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPromotionPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'discount_type' => ['sometimes', 'string', Rule::in(['percent', 'amount'])],
            'value_minor' => ['sometimes', 'integer', 'min:0'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'code' => ['prohibited'],
            'branch_id' => ['prohibited'],
        ];
    }
}
