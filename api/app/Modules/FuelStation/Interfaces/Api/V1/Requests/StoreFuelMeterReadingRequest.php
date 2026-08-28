<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Enums\FuelReadingSource;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Relevé de compteur FuelStation (issue #5798).
 *
 * Entrées strictes : valeur décimale bornée, horodatage requis, source
 * allowlistée, champs optionnels contrôlés.
 */
final class StoreFuelMeterReadingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reading_value' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'reading_at' => ['required', 'date'],
            'reading_at_local' => ['sometimes', 'string', 'max:40'],
            'operator_id' => ['sometimes', 'string', 'max:64'],
            'shift_id' => ['sometimes', 'string', 'max:64'],
            'source' => ['sometimes', 'string', 'in:'.implode(',', FuelReadingSource::values())],
            'note' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
