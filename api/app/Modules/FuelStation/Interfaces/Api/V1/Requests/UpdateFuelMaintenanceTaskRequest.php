<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une tâche de maintenance (FUEL-010, #5804).
 */
class UpdateFuelMaintenanceTaskRequest extends FormRequest
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
        return [
            'title' => ['nullable', 'string', 'max:160'],
            'task_type' => ['nullable', Rule::in(FuelMaintenanceTask::TYPES)],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(FuelMaintenanceTask::STATUSES)],
            'assigned_to' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
