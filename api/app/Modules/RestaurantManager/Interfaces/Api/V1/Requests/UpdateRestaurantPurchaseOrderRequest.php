<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-502 (#6201) — Validation stricte de mise à jour d'un bon de commande.
 */
class UpdateRestaurantPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPurchaseOrderPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
