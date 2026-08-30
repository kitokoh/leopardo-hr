<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement / mise à jour d'une présence (EDU-010, #5826).
 */
class RecordEduAttendanceRequest extends FormRequest
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
            'class_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'session_date' => ['nullable', 'date'],
            'session_label' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:present,absent,late,excused'],
            'reason' => ['nullable', 'string', 'max:255'],
            'justified' => ['nullable', 'boolean'],
        ];
    }
}
