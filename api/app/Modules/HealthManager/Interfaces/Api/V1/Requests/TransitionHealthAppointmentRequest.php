<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de statut d'un rendez-vous — HC-004 (#7788).
 *
 * Le statut cible doit être connu (422 Laravel) ; la validité de la
 * transition elle-même est vérifiée par HealthAppointmentService
 * (422 HEALTH_INVALID_TRANSITION, spec §4).
 */
class TransitionHealthAppointmentRequest extends FormRequest
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
