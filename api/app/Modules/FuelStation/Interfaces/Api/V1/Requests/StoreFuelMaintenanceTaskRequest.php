<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une tâche de maintenance (FUEL-010, #5804).
 */
class StoreFuelMaintenanceTaskRequest extends FormRequest
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
            'incident_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_incidents', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'title' => ['required', 'string', 'max:160'],
            'task_type' => ['nullable', Rule::in(FuelMaintenanceTask::TYPES)],
            'scheduled_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
