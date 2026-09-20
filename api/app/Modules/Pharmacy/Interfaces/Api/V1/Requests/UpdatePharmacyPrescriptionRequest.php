<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'une ordonnance — PHARMA-006 (#7803). La référence n'est pas
 * modifiable (pièce réglementaire).
 */
class UpdatePharmacyPrescriptionRequest extends FormRequest
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
            'prescriber_id' => ['sometimes', 'integer', 'min:1'],
            'patient_name' => ['sometimes', 'string', 'max:191'],
            'patient_contact' => ['sometimes', 'nullable', 'string', 'max:191'],
            'prescribed_at' => ['sometimes', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
