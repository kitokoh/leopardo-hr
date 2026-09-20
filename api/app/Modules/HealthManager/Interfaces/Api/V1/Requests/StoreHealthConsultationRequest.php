<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une consultation médicale — HC-005 (#7789).
 *
 * Les constantes vitales sont un tableau STRUCTURÉ (clés bornées) stocké
 * chiffré au repos (`vitals_encrypted`, cast `encrypted:array`).
 * L'appartenance tenant de patient_id / practitioner_id / appointment_id
 * est contrôlée côté contrôleur (404 fail-closed, jamais de fuite).
 */
class StoreHealthConsultationRequest extends FormRequest
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
            'patient_id' => ['required', 'integer'],
            // Praticien : forcé à la fiche de l'acteur (praticien) ; la
            // direction peut le préciser explicitement (contrôleur).
            'practitioner_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'consulted_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'clinical_exam' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'vitals' => ['nullable', 'array:weight_kg,height_cm,blood_pressure,temperature_c,pulse_bpm'],
            'vitals.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'vitals.height_cm' => ['nullable', 'numeric', 'min:0'],
            'vitals.blood_pressure' => ['nullable', 'string', 'max:20'],
            'vitals.temperature_c' => ['nullable', 'numeric'],
            'vitals.pulse_bpm' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
