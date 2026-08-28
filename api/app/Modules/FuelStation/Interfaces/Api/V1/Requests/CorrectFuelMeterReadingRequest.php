<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correction d'un relevé FuelStation (issue #5798).
 */
final class CorrectFuelMeterReadingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_value' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
