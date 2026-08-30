<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Affectation d'un enseignant à une matière dans une classe (EDU-010).
 * L'enseignant (employee_id RH) doit appartenir au même tenant (contrôle
 * service EMPLOYEE_OUTSIDE_TENANT).
 */
class AssignEduTeacherRequest extends FormRequest
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
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('edu_subjects', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'teacher_id' => ['required', 'integer'],
        ];
    }
}
