<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-604 (#6209) — Validation stricte de création d'une zone de livraison.
 *
 * Nom unique par (tenant, branche) ; frais et minimum en minor units.
 */
class StoreRestaurantDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryZonePolicy::create() tranche
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
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_delivery_zones', 'name')->where(
                    fn (Builder $q) => $q->where('company_id', $actor->company_id)
                        ->where('branch_id', (int) $this->input('branch_id'))
                ),
            ],
            'fee_minor' => ['required', 'integer', 'min:0'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
