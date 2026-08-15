<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de POST /calendar/sync — QA wave 2026-08-14 T007 (#2232).
 *
 * L'endpoint ne reçoit aucune entrée utilisateur (la synchro porte sur les
 * connexions de l'employé courant) : le FormRequest formalise le pipeline de
 * validation (auth + throttle) et documente l'absence d'entrée.
 */
class CalendarSyncRequest extends FormRequest
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
        return [];
    }
}
