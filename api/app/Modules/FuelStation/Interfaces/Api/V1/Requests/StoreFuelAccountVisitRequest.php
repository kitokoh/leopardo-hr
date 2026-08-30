<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelAccountVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une visite sur un compte professionnel (FUEL-016, #5810).
 *
 * Idempotente par external_id (rejeu sans doublon) ; notes redacted.
 */
class StoreFuelAccountVisitRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'visited_at' => ['nullable', 'date'],
            'purpose' => ['nullable', Rule::in(FuelAccountVisit::PURPOSES)],
            'notes_redacted' => ['nullable', 'string', 'max:2000'],
            'external_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}
