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

    /**
     * Le provider est un paramètre de ROUTE (`DELETE /calendar/disconnect/{provider}`),
     * pas un champ du corps de requête : sans ce merge, `required` échoue
     * toujours sur un DELETE sans body (422 « Certains champs sont incorrects »
     * même pour un provider connu — régression vue en CI, issue #5201).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }
}
