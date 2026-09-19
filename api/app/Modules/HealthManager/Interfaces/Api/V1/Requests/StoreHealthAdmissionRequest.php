<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admission d'un patient (HC-006, #7790).
 *
 * Patient, praticien référent (ACTIF), service et lit TOUJOURS du tenant
 * de l'acteur (Rule::exists scopées — cross-tenant = 422). La DISPONIBILITÉ
 * du lit est vérifiée sous transaction côté contrôleur (409 sinon) ; le
 * statut n'est jamais accepté depuis la requête.
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
                'required',
                'integer',
                Rule::exists('health_departments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'bed_id' => [
                'required',
                'integer',
                Rule::exists('health_beds', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'reason' => ['required', 'string', 'max:255'],
            'admitted_at' => ['nullable', 'date'],
            'expected_discharge_at' => ['nullable', 'date'],
        ];
    }
}
