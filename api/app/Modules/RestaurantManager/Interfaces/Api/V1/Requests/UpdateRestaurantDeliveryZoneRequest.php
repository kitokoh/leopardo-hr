<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-604 (#6209) — Mise à jour d'une zone de livraison.
 */
class UpdateRestaurantDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryZonePolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'fee_minor' => ['sometimes', 'integer', 'min:0'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
            'branch_id' => ['prohibited'],
        ];
    }
}
