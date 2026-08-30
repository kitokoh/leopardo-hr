<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Résolution d'un incident FuelStation (FUEL-010, #5804).
 * Notes de résolution obligatoires (traçabilité — jamais de résolution muette).
 */
class ResolveFuelIncidentRequest extends FormRequest
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
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
