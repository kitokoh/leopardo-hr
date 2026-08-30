<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-305 (#6186) — Validation stricte de création d'un fournisseur.
 *
 * Les coordonnées (téléphone, email, adresse) sont optionnelles ; le nom
 * est obligatoire. L'autorisation est tranchée par
 * `RestaurantSupplierPolicy::create()`.
 */
class StoreRestaurantSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantSupplierPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
