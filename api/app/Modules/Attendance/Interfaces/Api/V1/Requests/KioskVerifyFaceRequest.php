<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ATT-004 (#6769) — vérification faciale au pointage kiosque (contrat strict).
 *
 * La capture est une image bornée (type/taille validés) ; `device_event_id`
 * porte l'idempotence côté appareil (BIO-007 #6772) et sert de corrélation
 * d'audit (BIO-008 #6773) — jamais de gabarit dans les réponses.
 */
final class KioskVerifyFaceRequest extends FormRequest
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
            'capture' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'action' => ['nullable', 'in:check_in,check_out'],
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
            'device_event_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
