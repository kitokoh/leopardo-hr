<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #2232 — DELETE /calendar/disconnect/{provider} écrivait sans
 * validation : un provider inconnu passait jusqu'au service. Le paramètre
 * de route est validé (google/outlook/caldav) → 422 au lieu d'un état
 * incohérent.
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

    protected function validationData(): array
    {
        return [
            'provider' => $this->route('provider'),
        ];
    }
}
