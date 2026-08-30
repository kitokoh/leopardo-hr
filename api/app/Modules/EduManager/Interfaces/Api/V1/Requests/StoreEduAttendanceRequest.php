<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie de présence pour une classe (EDU-005, #5821 / EDU-010).
 * Idempotente (UNIQUE class+student+date) ; statut allowlisté.
 */
class StoreEduAttendanceRequest extends FormRequest
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
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(EduAttendance::STATUSES)],
            'reason' => ['nullable', 'string', 'max:50'],
            'justification' => ['nullable', 'string', 'max:500'],
        ];
    }
}
