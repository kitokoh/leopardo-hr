<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Replanification d'un rendez-vous (HC-004, #7788) — mêmes gardes tenant
 * que la création ; le statut ne passe JAMAIS par ici (endpoint de
 * transition dédié).
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
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
