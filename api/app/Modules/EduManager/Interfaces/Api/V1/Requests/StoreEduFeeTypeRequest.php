<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un type de frais scolaire (EDU-016, #5832).
 * Code unique par tenant (contrainte en base, EDU_FEE_TYPE_CODE_TAKEN).
 */
class StoreEduFeeTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_frequency' => ['required', Rule::in(EduFeeType::FREQUENCIES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
