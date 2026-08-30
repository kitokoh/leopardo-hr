<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un créneau d'emploi du temps (EDU-006, #5822 / EDU-010).
 * Conflits classe/enseignant contrôlés par le service.
 */
class StoreEduCourseSlotRequest extends FormRequest
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
            'class_id' => [
                'required',
                'integer',
                Rule::exists('edu_classes', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('edu_subjects', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('edu_academic_years', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'teacher_id' => ['nullable', 'integer'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'cancelled'])],
        ];
    }
}
