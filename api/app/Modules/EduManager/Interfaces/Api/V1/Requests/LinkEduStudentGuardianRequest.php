<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rattachement d'un responsable légal à un élève — EDU-002 (#5818).
 *
 * Le `guardian_id` doit appartenir au MÊME tenant que l'élève (contrôle
 * `Rule::exists` borné au tenant courant — une référence cross-tenant est
 * refusée en 422, jamais insérée). Les deux drapeaux de portée
 * (`can_view_grades`, `can_receive_notifications`) sont explicites : par
 * défaut un responsable ne voit PAS les notes.
 */
class LinkEduStudentGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation portée par la policy (EduStudentGuardianPolicy::create)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'guardian_id' => [
                'required',
                'integer',
                Rule::exists('edu_guardians', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'relationship_code' => ['nullable', Rule::in(EduGuardian::RELATIONSHIPS)],
            'can_view_grades' => ['nullable', 'boolean'],
            'can_receive_notifications' => ['nullable', 'boolean'],
        ];
    }
}
