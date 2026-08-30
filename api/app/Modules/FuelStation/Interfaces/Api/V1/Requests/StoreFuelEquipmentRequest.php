<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5805 — Création d'un équipement : pompe, cuve ou produit (FUEL-011).
 *
 * La validation des champs spécifiques est appliquée selon le type d'équipement
 * (les contrôleurs passent les champs allowlistés).
 */
class StoreFuelEquipmentRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\-_]+$/'],
            'name' => ['nullable', 'string', 'max:150'],
            'unit_code' => ['nullable', 'string', 'max:20'],
            'product_type' => ['nullable', 'string', 'max:40'],
            'capacity_minor' => ['nullable', 'integer', 'min:1'],
            'current_level_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', 'in:active,inactive,retired'],
        ];
    }
}
