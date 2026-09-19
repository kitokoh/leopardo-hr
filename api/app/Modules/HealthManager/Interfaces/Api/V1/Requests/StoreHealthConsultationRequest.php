<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une consultation (HC-005, #7789).
 *
 * Patient et rendez-vous TOUJOURS du tenant de l'acteur (Rule::exists
 * scopées — cross-tenant = 422). `practitioner_id` : implicite pour un
 * praticien (SA consultation) ; la direction non praticienne DOIT le
 * fournir (praticien ACTIF du tenant) — résolu côté contrôleur.
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('health_patients', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'practitioner_id' => [
                'nullable',
                'integer',
                Rule::exists('health_practitioners', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('status', 'active')
                ),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('health_appointments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'consulted_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
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
