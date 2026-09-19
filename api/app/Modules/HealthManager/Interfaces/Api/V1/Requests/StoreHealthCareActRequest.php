<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un acte du catalogue tarifaire (HC-007, #7791). Code unique
 * PAR TENANT (422 sinon), catégorie bornée, prix >= 0.
 */
class StoreHealthCareActRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('health_care_acts', 'code')
                    ->where('company_id', $actor?->company_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', Rule::in(HealthCareAct::CATEGORIES)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
