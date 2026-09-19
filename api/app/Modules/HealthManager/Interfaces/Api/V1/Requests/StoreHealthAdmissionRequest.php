<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admission d'un patient (hospitalisation) — HC-006 (#7790).
 *
 * Le service (`HealthAdmissionService::admit`) porte l'invariant lit
 * `free` → `occupied` en transaction + verrou ; le service de rattachement
 * est dérivé du lit (salle → service). Appartenance tenant contrôlée au
 * contrôleur/service (404 fail-closed).
 */
class StoreHealthAdmissionRequest extends FormRequest
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
            'practitioner_id' => ['required', 'integer'],
            'bed_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'admitted_at' => ['nullable', 'date'],
            'expected_discharge_at' => ['nullable', 'date', 'after:admitted_at'],
        ];
    }
}
