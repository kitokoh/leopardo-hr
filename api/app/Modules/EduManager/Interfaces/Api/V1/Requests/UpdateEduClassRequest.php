<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une classe (EDU-010).
 */
class UpdateEduClassRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:191'],
            'level' => ['nullable', 'string', 'max:50'],
            'teacher_id' => ['nullable', 'integer'],
            'capacity' => ['nullable', 'integer', 'gt:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
