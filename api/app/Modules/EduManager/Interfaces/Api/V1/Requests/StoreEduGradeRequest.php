<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie d'une note (EDU-007, #5823 / EDU-010). Le barème [0, max_score]
 * est contrôlé par le service (EDU_GRADE_OUT_OF_RANGE).
 */
class StoreEduGradeRequest extends FormRequest
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
            'student_id' => [
                'required',
                'integer',
                Rule::exists('edu_students', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'score' => ['required', 'numeric'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
