<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'endpoint self-service `/me/attendance/anomalies` (HR).
 *
 * Règles STRICTEMENT identiques à
 * `Attendance\Interfaces\Api\V1\Requests\AttendanceAnomaliesRequest` —
 * dupliquées volontairement pour ne plus importer une classe d'un autre
 * module (règle d'isolation #5584, BOS-023 cycle 3 #8299). `employee_id`
 * reste validé ici même s'il est ensuite forcé à l'appelant par le contrat
 * `AttendanceAnomalySummarizer` : un employee_id invalide doit continuer
 * de recevoir un 422 (comportement historique préservé).
 *
 * Toute évolution des règles doit être répercutée depuis/vers la classe
 * source d'Attendance tant que les deux coexistent.
 */
class OwnAttendanceAnomaliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'integer',
                'min:1',
                // getAttribute() ≡ accès magique ->company_id (Eloquent __get),
                // typé pour PHPStan strict sur l'union des guards.
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->getAttribute('company_id')),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
