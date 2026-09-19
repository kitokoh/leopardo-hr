<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Modules\HealthManager\Domain\Models\HealthPatient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un patient — HC-003 (#7787).
 *
 * Le MRN n'est JAMAIS accepté du client (généré serveur,
 * HealthPatientNumberService). PII/données médicales transmises en clair
 * puis chiffrées au repos par les casts `encrypted` du modèle.
 */
class StoreHealthPatientRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:191'],
            'sex' => ['required', Rule::in(HealthPatient::SEXES)],
            'birth_date' => ['nullable', 'date'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'insurance_provider' => ['nullable', 'string', 'max:191'],
            'insurance_number' => ['nullable', 'string', 'max:100'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'medical_history' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
