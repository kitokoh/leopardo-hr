<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduClass;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription d'un élève dans une classe (EDU-011, #5827).
 * Idempotente : UNIQUE (company_id, class_id, student_id).
 */
class StoreEduClassEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La classe cible est résolue AVANT la validation : un acteur qui
        // vise la classe d'un AUTRE tenant doit recevoir 404 (l'existence de
        // la ressource n'est pas révélée), et non 422 sur ses champs — sinon
        // l'ordre validation → autorisation fuit le contrat à un tenant tiers.
        /** @var Employee|null $actor */
        $actor = $this->user();
        $class = $this->route('class');

        abort_unless(
            $actor instanceof Employee
            && $class instanceof EduClass
            && $class->company_id === $actor->company_id,
            404
        );

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
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('edu_academic_years', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'enrolled_at' => ['nullable', 'date'],
        ];
    }
}
