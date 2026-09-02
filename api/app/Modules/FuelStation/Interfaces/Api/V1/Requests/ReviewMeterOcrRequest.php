<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI-002 (#6771) — revue humaine d'une demande OCR `needs_review`.
 *
 * accept : la valeur lue est enregistrée (valeur corrigée possible via
 * reading_value_minor) ; reject : motif libre (rappel : le motif est tracé
 * en error_code, colonne string(60)).
 */
class ReviewMeterOcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC : manager (middleware api.manager sur la route).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accepted' => ['required', 'boolean'],
            'reading_value_minor' => ['required_if:accepted,true', 'integer', 'min:0', 'max:99999999999999'],
            'reading_unit' => ['sometimes', 'string', 'in:l,ml,gal,ft3'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
