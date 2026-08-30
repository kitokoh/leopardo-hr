<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Affectation d'un employé à un shift pour une date (FUEL-005, #5799).
 *
 * L'appartenance de l'employé au tenant est contrôlée dans
 * `FuelShiftService::assign()` (EMPLOYEE_OUTSIDE_TENANT) ; le chevauchement
 * horaire est contrôlé par `assertNoOverlap()` (SHIFT_OVERLAP).
 */
class StoreFuelShiftAssignmentRequest extends FormRequest
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
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'assignment_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
