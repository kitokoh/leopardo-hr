<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un événement de carrière (plans de carrière, issue #5259).
 * Les cibles (poste/département) et l'employé sont scopés au tenant de
 * l'acteur : impossible de référencer une entité d'une autre entreprise.
 */
class StoreCareerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee|null $user */
        $user = $this->user();

        return $user?->isManager() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user?->company_id;

        return [
            'employee_id' => [
                'required', 'integer', 'min:1',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'type' => ['required', 'in:promotion,raise,transfer,title_change'],
            'to_position_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('positions', 'id')->where('company_id', $companyId),
            ],
            'to_department_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('departments', 'id')->where('company_id', $companyId),
            ],
            'to_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => __('employees.career_event_employee_not_found'),
            'to_position_id.exists' => __('employees.career_event_position_not_found'),
            'to_department_id.exists' => __('employees.career_event_department_not_found'),
            'effective_date.required' => __('employees.career_event_effective_date_required'),
            'reason.required' => __('employees.career_event_reason_required'),
        ];
    }
}
