<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'une consultation médicale — HC-005 (#7789).
 *
 * Auteur (sa fiche praticien) ou direction uniquement (Policy). Le patient
 * et le praticien sont IMMUABLES — seul le contenu médical évolue.
 */
class UpdateHealthConsultationRequest extends FormRequest
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
            'consulted_at' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clinical_exam' => ['sometimes', 'nullable', 'string'],
            'diagnosis' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'vitals' => ['sometimes', 'nullable', 'array:weight_kg,height_cm,blood_pressure,temperature_c,pulse_bpm'],
            'vitals.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'vitals.height_cm' => ['nullable', 'numeric', 'min:0'],
            'vitals.blood_pressure' => ['nullable', 'string', 'max:20'],
            'vitals.temperature_c' => ['nullable', 'numeric'],
            'vitals.pulse_bpm' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
