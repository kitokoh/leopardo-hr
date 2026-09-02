<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une matière (EDU-003, #5819 / EDU-010). Code unique par tenant.
 */
class StoreEduSubjectRequest extends FormRequest
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
            'campus_id' => [
                'nullable',
                'integer',
                Rule::exists('edu_campuses', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('edu_subjects', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:191'],
            'default_coefficient' => ['nullable', 'numeric', 'gt:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
