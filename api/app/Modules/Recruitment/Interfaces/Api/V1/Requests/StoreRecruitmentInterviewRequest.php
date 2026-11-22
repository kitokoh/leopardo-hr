<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Planification d'un entretien (#9732).
 *
 * `interviewer_id` est verifie dans le tenant de l'acteur : sans ce filtre, on
 * pourrait designer comme evaluateur l'employe d'une autre entreprise.
 */
final class StoreRecruitmentInterviewRequest extends FormRequest
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
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user?->company_id;

        return [
            'interviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'type' => 'required|in:phone,video,onsite,technical',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
        ];
    }
}
