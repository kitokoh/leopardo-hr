<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changement de statut d'une tâche de maintenance (FUEL-010, #5804).
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
            'status' => ['required', Rule::in(FuelMaintenanceTask::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
