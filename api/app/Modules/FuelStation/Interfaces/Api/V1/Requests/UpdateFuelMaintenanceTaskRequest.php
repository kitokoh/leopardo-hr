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
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'status' => ['nullable', Rule::in(FuelMaintenanceTask::STATUSES)],
            'assigned_to' => ['nullable', 'integer'],
            'scheduled_for' => ['nullable', 'date'],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
