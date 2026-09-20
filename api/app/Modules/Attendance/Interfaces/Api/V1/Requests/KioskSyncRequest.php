<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ATT-004 (#6769) / BIO-007 (#6772) — synchronisation d'événements offline
 * kiosque (contrat strict).
 *
 * - `events` : liste bornée d'événements (borne configurable, défaut 500) ;
 *   chaque événement porte l'identifiant `device_event_id` (réconciliation),
 *   la méthode réellement utilisée (fidélité BIO-006) et l'horodatage local ;
 * - `device_state` (optionnel, rétro-compatible) : enveloppe signée
 *   (counter monotone + nonce + signed_at + HMAC-SHA256) qui permet au
 *   serveur de rejeter un rejeu ou une falsification du batch offline.
 */
final class KioskSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentification appareil : middleware kiosk.device (X-Kiosk-Token).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxEvents = (int) config('attendance.kiosk.offline.max_events_per_batch', 500);

        return [
            'events' => ['required', 'array', 'max:'.$maxEvents],
            'events.*.identifier' => ['nullable', 'string', 'max:150'],
            'events.*.action' => ['nullable', 'in:check_in,check_out'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.device_event_id' => ['nullable', 'string', 'max:100'],
            'events.*.external_event_id' => ['nullable', 'string', 'max:100'],
            'events.*.biometric_type' => ['nullable', 'in:fingerprint,face,mixed'],
            // BIO-006 (#6767) : la méthode réellement utilisée est préservée
            // dans la synchro offline (badge → card en persistance).
            // #7958 : `manual` = pointage déclaratif (identité non prouvée) —
            // c'est aussi la valeur de dégradation des biométries sans preuve.
            'events.*.method' => ['nullable', 'in:fingerprint,face,badge,pin,manager,card,manual'],
            // #7958 : preuve device biométrique (réservé future intégration
            // SDK lecteur — fail-closed tant qu'aucun vérificateur n'existe).
            'events.*.biometric_proof' => ['nullable', 'string', 'max:2048'],
            // #7958 : le bridge marque les pointages hors-ligne déclaratifs
            // (ex. PIN saisi sans vérification) pour l'audit de sync.
            'events.*.unverified' => ['nullable', 'boolean'],
            'events.*.work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
            // BIO-007 (#6772) : enveloppe d'intégrité du batch offline.
            'device_state' => ['nullable', 'array'],
            'device_state.counter' => ['required_with:device_state', 'integer', 'min:1'],
            'device_state.nonce' => ['required_with:device_state', 'string', 'max:64'],
            'device_state.signed_at' => ['required_with:device_state', 'date'],
            'device_state.integrity' => ['required_with:device_state', 'string', 'size:64'],
        ];
    }
}
