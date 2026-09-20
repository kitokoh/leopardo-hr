<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un acte de soins facturable — HC-007 (#7791).
 * Code unique par tenant ; catégorie bornée (spec §3).
 */
class StoreHealthCareActRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC porté par la policy (HealthCareActPolicy@create).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('health_care_acts', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'label' => ['required', 'string', 'max:191'],
            'category' => ['required', Rule::in(HealthCareAct::CATEGORIES)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
