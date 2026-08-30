<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-607 (#6212) — Création d'une promotion (code unique par tenant).
 */
class StoreRestaurantPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPromotionPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('restaurant_branches', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'code' => ['required', 'string', 'max:40', Rule::unique('restaurant_promotions', 'code')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'title' => ['required', 'string', 'max:150'],
            'discount_type' => ['required', 'string', Rule::in(['percent', 'amount'])],
            'value_minor' => ['required', 'integer', 'min:0'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
