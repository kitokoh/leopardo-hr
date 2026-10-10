<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création/mise à jour d'une grille tarifaire VTC (BC-34 VTC, VTC-06/#8362).
 *
 * Montants en MINOR UNITS (entiers ≥ 0). `is_default` est exclusif par
 * tenant : poser une grille par défaut retire le drapeau des autres
 * (transaction côté contrôleur).
 */
final class FareProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'base_minor' => ['required', 'integer', 'min:0'],
            'per_km_minor' => ['required', 'integer', 'min:0'],
            'per_minute_minor' => ['required', 'integer', 'min:0'],
            'minimum_minor' => ['required', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
