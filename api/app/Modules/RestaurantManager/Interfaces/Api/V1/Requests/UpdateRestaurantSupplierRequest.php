<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-305 (#6186) — Validation stricte de modification d'un fournisseur.
 *
 * Mêmes contraintes qu'à la création, en `sometimes` pour permettre un
 * `PUT` partiel. L'autorisation est tranchée par
 * `RestaurantSupplierPolicy::update()`.
 */
class UpdateRestaurantSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantSupplierPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
