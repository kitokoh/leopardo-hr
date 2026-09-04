<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-606 (#6211) — Programme de fidélité (création).
 */
class StoreRestaurantLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyProgramPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'points_per_amount_minor' => ['required', 'integer', 'min:1'],
            'redeem_rate_minor' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
