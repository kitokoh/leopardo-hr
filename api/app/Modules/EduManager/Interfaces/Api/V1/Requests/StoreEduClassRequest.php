<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une classe (EDU-003, #5819 / EDU-010). Code unique par
 * (tenant, année) ; campus et année du même tenant (FK composites).
 */
class StoreEduClassRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('edu_campuses', 'id')->where(
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
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:191'],
            'level' => ['nullable', 'string', 'max:50'],
            'teacher_id' => ['nullable', 'integer'],
            'capacity' => ['nullable', 'integer', 'gt:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
