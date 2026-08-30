<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création / mise à jour d'un produit du catalogue FuelStation
 * (FUEL-011, #5805).
 */
class StoreFuelProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'unit_code' => ['nullable', 'in:l,gal'],
            'status' => ['nullable', 'in:active,inactive'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
