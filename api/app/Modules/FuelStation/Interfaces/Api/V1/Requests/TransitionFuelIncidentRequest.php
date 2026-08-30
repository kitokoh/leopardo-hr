<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de workflow d'un incident FuelStation (FUEL-010, #5804).
 *
 * Les transitions illégales sont rejetées au niveau service
 * (`FuelIncident::TRANSITIONS`) ; `resolution_notes` est exigée pour le
 * statut `resolved`.
 */
class TransitionFuelIncidentRequest extends FormRequest
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
            'status' => ['required', Rule::in(FuelIncident::STATUSES)],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer'],
        ];
    }
}
