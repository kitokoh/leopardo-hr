<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5804 — Mise à jour d'une tâche de maintenance (FUEL-010).
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
            'type' => ['sometimes', 'string', 'in:preventive,corrective'],
            'title' => ['sometimes', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:open,in_progress,cancelled'],
        ];
    }
}
