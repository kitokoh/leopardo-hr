<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Planification d'un entretien (#9732).
 */
final class StoreFarmMaintenanceRequest extends FormRequest
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
            'maintenance_type' => ['required', 'string', Rule::in(FarmMaintenanceType::values())],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'planned_at' => ['nullable', 'date'],
            'meter_value_at' => ['nullable', 'numeric', 'min:0'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'supplier_id' => ['nullable', 'integer'],
            'breakdown_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_maintenance_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
