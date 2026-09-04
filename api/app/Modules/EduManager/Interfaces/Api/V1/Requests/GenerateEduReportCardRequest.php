<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduReportCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Génération d'un bulletin (EDU-008, #5824 / EDU-010).
 */
class GenerateEduReportCardRequest extends FormRequest
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
            'student_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'period' => ['required', Rule::in(EduReportCard::PERIODS)],
        ];
    }
}
