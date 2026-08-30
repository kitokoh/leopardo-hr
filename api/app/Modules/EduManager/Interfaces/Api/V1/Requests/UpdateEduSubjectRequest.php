<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une matière (EDU-010).
 */
class UpdateEduSubjectRequest extends FormRequest
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
            'campus_id' => ['nullable', 'integer'],
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:191'],
            'default_coefficient' => ['nullable', 'numeric', 'gt:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
