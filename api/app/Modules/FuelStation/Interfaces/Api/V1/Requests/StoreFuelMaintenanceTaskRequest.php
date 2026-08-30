<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5804 — Création d'une tâche de maintenance (FUEL-010).
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
        return [
            'station_id' => ['nullable', 'integer', 'exists:fuel_stations,id'],
            'equipment_type' => ['nullable', 'string', 'in:pump,tank,meter,site,other'],
            'equipment_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'in:preventive,corrective'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
