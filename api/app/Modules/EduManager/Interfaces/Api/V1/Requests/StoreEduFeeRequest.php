<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un frais scolaire (EDU-016, #5832).
 *
 * `external_reference` → rejeu idempotent (unique par tenant, EduFeeService) ;
 * montant strictement positif (CHECK) ; élève du même tenant (FK composite).
 */
class StoreEduFeeRequest extends FormRequest
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
            'admission_id' => [
                'nullable',
                'integer',
                Rule::exists('edu_admissions', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'label' => ['required', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(EduFee::STATUSES)],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
