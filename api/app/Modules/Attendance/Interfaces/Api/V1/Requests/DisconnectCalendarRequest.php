<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de DELETE /calendar/disconnect/{provider} — QA wave 2026-08-14
 * T007 (#2232) : le provider était accepté tel quel (aucune validation).
 * La règle porte sur le paramètre de route `provider`.
 */
class DisconnectCalendarRequest extends FormRequest
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
            'provider' => ['required', 'string', 'in:google,outlook,caldav'],
        ];
    }
}
