<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HOSP-006 (#7948, spec §6) — réservation en ligne depuis la fiche publique.
 *
 * Aucun montant accepté : `total_amount_minor` est calculé SERVEUR
 * (base_price_minor × nuits) — jamais depuis le payload (pattern
 * CreateBookingAction Travel).
 */
class StoreHospitalityPublicReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'min:1'],
            'guest_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:30'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
