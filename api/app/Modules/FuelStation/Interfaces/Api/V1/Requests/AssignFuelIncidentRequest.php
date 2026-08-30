<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Assignation d'un incident à un employé du tenant (FUEL-010, #5804).
 */
class AssignFuelIncidentRequest extends FormRequest
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
        ];
    }
}
