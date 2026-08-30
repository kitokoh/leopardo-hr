<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correction de présence (EDU-005, #5821 / EDU-010) — versionnée côté
 * service (journal edu_attendance_corrections).
 */
class CorrectEduAttendanceRequest extends FormRequest
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
            'status' => ['required', Rule::in(EduAttendance::STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
