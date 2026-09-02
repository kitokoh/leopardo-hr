<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ATT-004 (#6769) — pointage kiosque multi-méthodes (contrat strict).
 *
 * Allowlist stricte : identifiant borné, action/work_type/méthode sur des
 * listes fermées, `device_event_id` optionnel (réconciliation BIO-007 #6772).
 * La matrice serveur reste la source de vérité (BIO-006 #6767) : une méthode
 * désactivée est refusée côté service, jamais par l'interface seule.
 */
final class KioskPunchRequest extends FormRequest
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
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
            // BIO-006 (#6767) : méthode réellement utilisée + validation
            // manager pour les cas exceptionnels.
            'method' => ['nullable', 'in:fingerprint,face,badge,pin,manager,card'],
            'manager_employee_id' => ['nullable', 'integer', 'min:1'],
            // BIO-007 (#6772) : identifiant d'événement appareil (réconciliation
            // idempotente quand l'Idempotency-Key n'est pas fournie).
            'device_event_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
