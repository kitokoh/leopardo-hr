<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ATT-004 (#6769) — démarrage d'un enrôlement biométrique depuis le kiosque.
 *
 * Le gabarit (`template_payload`) est une chaîne opaque (capture chiffrée par
 * le kiosque) : le serveur ne fait aucune hypothèse de format fournisseur.
 * La réponse ne renvoie JAMAIS le gabarit (réponse neutre ATT-004) ;
 * `correlation_id` rend le démarrage idempotent (BIO-002 #6763).
 */
final class KioskEnrollmentStartRequest extends FormRequest
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
            'method' => ['required', 'in:face,fingerprint'],
            'template_payload' => ['required', 'string', 'min:16', 'max:65535'],
            'provider' => ['required', 'string', 'max:60'],
            'correlation_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]{8,100}$/'],
        ];
    }
}
