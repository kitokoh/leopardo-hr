<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Résolution d'un incident (FUEL-010, #5804) — notes obligatoires.
 */
class ResolveFuelIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Notes de résolution — la présence est ENFORCÉE au niveau service
     * (FUEL_INCIDENT_RESOLUTION_NOTES_REQUIRED) pour un contrôle unique ;
     * le FormRequest ne fait que borner la longueur.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
