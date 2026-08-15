<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #2232 — POST /calendar/sync acceptait n'importe quel payload.
 * Le paramètre optionnel `provider` (filtre de synchronisation) est
 * désormais validé : une valeur hors google/outlook/caldav → 422.
 * Comportement inchangé quand le champ est absent.
 */
class SyncCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'in:google,outlook,caldav'],
        ];
    }
}
