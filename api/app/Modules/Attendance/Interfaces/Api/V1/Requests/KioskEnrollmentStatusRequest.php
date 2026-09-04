<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ATT-004 (#6769) — consultation de l'état d'enrôlement d'un employé depuis
 * le kiosque.
 *
 * Réponse neutre : statuts, versions et horodatages uniquement — jamais de
 * gabarit ni de capture (BIO-003 #6764, BIO-008 #6773).
 */
final class KioskEnrollmentStatusRequest extends FormRequest
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
        ];
    }
}
