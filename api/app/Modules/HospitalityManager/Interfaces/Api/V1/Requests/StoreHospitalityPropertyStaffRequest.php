<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Affectation d'un employé à un établissement — HOSP-003 (#7945).
 *
 * Le contrôle cross-tenant strict de l'employé (422 EMPLOYEE_OUTSIDE_TENANT)
 * est métier (`HospitalityPropertyStaffService`), pas une simple règle
 * d'existence — volontairement pas de `Rule::exists` ici.
 */
class StoreHospitalityPropertyStaffRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'min:1'],
            'role' => ['nullable', 'string', 'max:80'],
        ];
    }
}
