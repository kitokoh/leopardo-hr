<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Facturation d'un frais à un élève (EDU-016, #5832).
 * `external_id` → rejeu idempotent ; montant optionnel (défaut : tarif du
 * type de frais, figé à la création pour une traçabilité comptable stable).
 */
class StoreEduFeeChargeRequest extends FormRequest
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
            'fee_type_id' => [
                'required',
                'integer',
                Rule::exists('edu_fee_types', 'id')->where(
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
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'due_date' => ['nullable', 'date'],
            'external_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
