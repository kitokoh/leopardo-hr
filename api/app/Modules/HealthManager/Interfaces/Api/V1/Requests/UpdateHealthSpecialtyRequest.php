<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthSpecialty;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une spécialité médicale — HC-002 (#7786).
 */
class UpdateHealthSpecialtyRequest extends FormRequest
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

        /** @var HealthSpecialty|null $specialty */
        $specialty = $this->route('specialty');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('health_specialties', 'code')
                    ->ignore($specialty?->getAttribute('id'))
                    ->where(
                        fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                    ),
            ],
        ];
    }
}
