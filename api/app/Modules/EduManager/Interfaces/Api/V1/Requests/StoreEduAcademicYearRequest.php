<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une année scolaire (EDU-003, #5819 / EDU-010).
 *
 * Nom unique par tenant ; période cohérente contrôlée par le service
 * (EDU_ACADEMIC_YEAR_PERIOD / EDU_ACADEMIC_YEAR_OVERLAP).
 */
class StoreEduAcademicYearRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('edu_academic_years', 'name')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['nullable', Rule::in(['active', 'closed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
