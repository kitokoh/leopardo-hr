<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un acte de soins — HC-007 (#7791).
 *
 * Modifier le prix du catalogue ne réécrit JAMAIS les lignes de factures
 * existantes (prix figés à la facturation, spec §4).
 */
class UpdateHealthCareActRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC porté par la policy (HealthCareActPolicy@update).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        /** @var HealthCareAct|null $careAct */
        $careAct = $this->route('careAct');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('health_care_acts', 'code')
                    ->ignore($careAct?->getAttribute('id'))
                    ->where(
                        fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                    ),
            ],
            'label' => ['sometimes', 'string', 'max:191'],
            'category' => ['sometimes', Rule::in(HealthCareAct::CATEGORIES)],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
