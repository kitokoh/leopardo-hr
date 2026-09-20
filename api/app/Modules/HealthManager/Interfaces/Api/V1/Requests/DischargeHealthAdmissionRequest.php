<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sortie d'hospitalisation — HC-006 (#7790).
 *
 * `discharged_at` + statut `discharged` + lit libéré (service, transaction
 * + verrou) ; double sortie → 422.
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
            'discharge_notes' => ['nullable', 'string'],
        ];
    }
}
