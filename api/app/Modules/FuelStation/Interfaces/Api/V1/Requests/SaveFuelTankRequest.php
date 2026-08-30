<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / remplacement d'une cuve (FUEL-011, #5805).
 *
 * Capacités en unités mineures entières (jamais de flottants métier).
 */
class SaveFuelTankRequest extends FormRequest
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
            'product_type' => ['required', 'string', 'max:40'],
            'capacity_minor' => ['required', 'integer', 'gt:0', 'max:999999999999'],
            'current_level_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'status' => ['required', Rule::in(FuelTank::STATUSES)],
        ];
    }
}
