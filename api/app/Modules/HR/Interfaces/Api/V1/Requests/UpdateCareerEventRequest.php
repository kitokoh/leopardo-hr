<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Édition d'un événement de carrière (issue #5259). L'employé et les champs
 * `from_*` (snapshot) sont immuables : on ne peut pas réassigner un
 * événement à un autre employé, ni réécrire l'historique.
 */
class UpdateCareerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'type' => ['sometimes', 'in:promotion,raise,transfer,title_change'],
            'to_position_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('positions', 'id')->where('company_id', $companyId),
            ],
            'to_department_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('departments', 'id')->where('company_id', $companyId),
            ],
            'to_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'effective_date' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_position_id.exists' => __('employees.career_event_position_not_found'),
            'to_department_id.exists' => __('employees.career_event_department_not_found'),
        ];
    }
}
