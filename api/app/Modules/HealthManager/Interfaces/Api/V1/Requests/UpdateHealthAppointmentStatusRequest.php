<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de statut d'un rendez-vous (HC-004, #7788) — le statut cible
 * doit être un statut connu ; la VALIDITÉ de la transition est vérifiée
 * ensuite contre HealthAppointment::TRANSITIONS (422 sinon).
 */
class UpdateHealthAppointmentStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(HealthAppointment::STATUSES)],
        ];
    }
}
