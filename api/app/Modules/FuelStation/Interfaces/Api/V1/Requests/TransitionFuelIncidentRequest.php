<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5804 — Transition de statut d'un incident (FUEL-010).
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
            'status' => ['required', 'string', 'in:assigned,in_progress,resolved,cancelled'],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
