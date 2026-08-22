<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de liste des événements de carrière (issue #5259).
 * `employee_id` est scopé au tenant ; l'employé non-manager ne voit que ses
 * propres événements (le filtrage RBAC est fait dans le controller).
 */
class CareerEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'employee_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'type' => ['nullable', 'in:promotion,raise,transfer,title_change'],
            'status' => ['nullable', 'in:pending,approved,rejected,applied'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => __('employees.career_event_employee_not_found'),
        ];
    }
}
