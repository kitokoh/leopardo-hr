<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un praticien — HC-002 (#7786). Le lien employé n'est pas
 * modifiable (pattern EduTeacher) ; service et spécialités du MÊME tenant.
 */
class UpdateHealthPractitionerRequest extends FormRequest
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
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('health_departments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'title' => ['sometimes', Rule::in(HealthPractitioner::TITLES)],
            'license_number' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(HealthPractitioner::STATUSES)],
            'specialty_ids' => ['sometimes', 'array'],
            'specialty_ids.*' => [
                'integer',
                Rule::exists('health_specialties', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
        ];
    }
}
