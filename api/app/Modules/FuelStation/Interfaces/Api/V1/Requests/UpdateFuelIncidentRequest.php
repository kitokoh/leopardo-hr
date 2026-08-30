<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Transitions d'incident (assign / resolve / close) — FUEL-010 (#5804).
 */
class UpdateFuelIncidentRequest extends FormRequest
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
            'assigned_to' => ['nullable', 'integer'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'closure_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:open,in_progress,resolved,closed'],
        ];
    }
}
