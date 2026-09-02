<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ATT-004 (#6769) — décision (activation / révocation) d'un enrôlement
 * biométrique depuis le kiosque.
 *
 * Toute décision exige la validation d'un manager ACTIF du même tenant
 * (BIO-006 #6767) : `manager_employee_id` est résolu et vérifié côté serveur
 * (KioskManagerGuard) — jamais de simple confiance dans l'interface.
 */
final class KioskEnrollmentDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentification appareil : middleware kiosk.device (X-Kiosk-Token).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'manager_employee_id' => ['required', 'integer', 'min:1'],
            'correlation_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]{8,100}$/'],
        ];
    }
}
