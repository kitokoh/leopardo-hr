<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un praticien — HC-002 (#7786). Employé RH du MÊME tenant,
 * unique par employé/tenant ; service et spécialités du MÊME tenant
 * (anti cross-tenant, fail-closed).
 */
class StoreHealthPractitionerRequest extends FormRequest
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
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
                Rule::unique('health_practitioners', 'employee_id')->where(
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
            'title' => ['nullable', Rule::in(HealthPractitioner::TITLES)],
            'license_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(HealthPractitioner::STATUSES)],
            'specialty_ids' => ['nullable', 'array'],
            'specialty_ids.*' => [
                'integer',
                Rule::exists('health_specialties', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
        ];
    }
}
