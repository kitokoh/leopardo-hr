<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un service médical — HC-002 (#7786).
 */
class UpdateHealthDepartmentRequest extends FormRequest
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

        /** @var HealthDepartment|null $department */
        $department = $this->route('department');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('health_departments', 'code')
                    ->ignore($department?->getAttribute('id'))
                    ->where(
                        fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                    ),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(HealthDepartment::STATUSES)],
        ];
    }
}
