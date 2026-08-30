<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un incident FuelStation (FUEL-010, #5804).
 * Sévérité/équipement allowlistés ; `equipment_id` optionnel (libre).
 */
class StoreFuelIncidentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'equipment_type' => ['nullable', Rule::in(FuelIncident::EQUIPMENT_TYPES)],
            'equipment_id' => ['nullable', 'integer'],
            'severity' => ['nullable', Rule::in(FuelIncident::SEVERITIES)],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
