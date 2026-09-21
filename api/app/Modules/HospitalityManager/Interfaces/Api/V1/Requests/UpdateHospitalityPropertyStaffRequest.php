<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour du rôle descriptif d'une affectation — HOSP-003 (#7945).
 */
class UpdateHospitalityPropertyStaffRequest extends FormRequest
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
            'role' => ['nullable', 'string', 'max:80'],
        ];
    }
}
