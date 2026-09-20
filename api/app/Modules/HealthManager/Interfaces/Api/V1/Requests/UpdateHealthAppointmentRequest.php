<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un rendez-vous — HC-004 (#7788).
 *
 * Le statut n'est PAS modifiable ici (machine à états via POST /status).
 * Références re-scopées tenant (422) ; cohérence starts_at/ends_at
 * re-vérifiée par le contrôleur quand un seul des deux bouge.
 */
class UpdateHealthAppointmentRequest extends FormRequest
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
                'sometimes',
                'integer',
                Rule::exists('health_patients', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'practitioner_id' => [
                'sometimes',
                'integer',
                Rule::exists('health_practitioners', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('health_departments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
