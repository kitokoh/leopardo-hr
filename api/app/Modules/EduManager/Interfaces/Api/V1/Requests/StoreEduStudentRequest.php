<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un élève (EDU-002, #5818 / EDU-010).
 *
 * `student_number` unique par tenant ; PII (birth_date) transmise chiffrée
 * (cast `encrypted` sur le modèle).
 */
class StoreEduStudentRequest extends FormRequest
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
            'student_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('edu_students', 'student_number')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'display_name' => ['required', 'string', 'max:191'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
