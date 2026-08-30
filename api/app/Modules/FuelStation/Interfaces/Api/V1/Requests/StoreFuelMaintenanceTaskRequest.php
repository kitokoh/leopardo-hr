<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une tâche de maintenance (FUEL-010, issue #5804).
 *
 * `incident_id` optionnel (tâche corrective dérivée d'un incident) ;
 * `assigned_to` doit être un employé du tenant (validé par le service via
 * le scope tenant) ; `due_at` optionnel pour piloter les alertes (FUEL-019).
 */
class StoreFuelMaintenanceTaskRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'station_id' => ['nullable', 'integer', 'exists:fuel_stations,id'],
            'incident_id' => ['nullable', 'integer', 'exists:fuel_incidents,id'],
            'title' => ['required', 'string', 'max:200'],
            'description_redacted' => ['nullable', 'string', 'max:2000'],
            'task_type' => ['nullable', Rule::in(FuelMaintenanceTask::TYPES)],
            'priority' => ['nullable', Rule::in(FuelMaintenanceTask::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
            'due_at' => ['nullable', 'date'],
            'external_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}
