<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un élève (EDU-010).
 */
class UpdateEduStudentRequest extends FormRequest
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
            'student_number' => ['sometimes', 'string', 'max:50'],
            'display_name' => ['sometimes', 'string', 'max:191'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
