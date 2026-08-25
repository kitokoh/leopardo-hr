<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la période comptable (YYYY-MM) pour le journal.
 * Issue #5234.
 */
class JournalPeriodRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.required' => 'La période est requise (format YYYY-MM).',
            'period.regex' => 'La période doit être au format YYYY-MM (ex. 2026-08).',
        ];
    }
}
