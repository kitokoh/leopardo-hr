<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'une consultation (HC-005, #7789) — contenu médical
 * uniquement : patient, praticien auteur et rendez-vous ne changent
 * JAMAIS après coup (intégrité du dossier médical).
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
            'reason' => ['sometimes', 'string', 'max:255'],
            'clinical_exam' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'temperature_c' => ['nullable', 'numeric', 'min:20', 'max:45'],
            'pulse_bpm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
