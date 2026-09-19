<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sortie d'hospitalisation (HC-006, #7790) : date de sortie optionnelle
 * (défaut : maintenant) et notes de sortie (chiffrées au repos).
 */
class DischargeHealthAdmissionRequest extends FormRequest
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
            'discharged_at' => ['nullable', 'date'],
            'discharge_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
