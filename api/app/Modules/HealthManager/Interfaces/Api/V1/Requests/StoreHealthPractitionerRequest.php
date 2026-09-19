<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un praticien (HC-002, #7786). `employee_id` doit appartenir au
 * MÊME tenant et ne peut être praticien qu'une fois ; spécialités n-n
 * scopées tenant.
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
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
                Rule::unique('health_practitioners', 'employee_id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'display_name' => ['required', 'string', 'max:191'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
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
