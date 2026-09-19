<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un dossier patient (HC-003, #7787). Le MRN n'est jamais
 * modifiable.
 */
class UpdateHealthPatientRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'sex' => ['sometimes', Rule::in(['female', 'male', 'other', 'unknown'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'insurance_provider' => ['nullable', 'string', 'max:191'],
            'insurance_number' => ['nullable', 'string', 'max:100'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'medical_history' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['active', 'deceased', 'archived'])],
        ];
    }
}
