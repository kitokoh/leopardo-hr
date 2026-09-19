<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Planification d'un rendez-vous (HC-004, #7788).
 *
 * Patient, praticien et service sont TOUJOURS du tenant de l'acteur
 * (Rule::exists scopées company_id — cross-tenant = 422) ; le statut n'est
 * JAMAIS accepté ici (cycle de vie via l'endpoint de transition validé).
 */
class StoreHealthAppointmentRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('health_practitioners', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('status', 'active')
                ),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('health_departments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
