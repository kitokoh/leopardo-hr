<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FUEL-004 — correction versionnée d'un relevé (motif obligatoire).
 */
class CorrectReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC réel dans FuelMeterReadingPolicy::correct.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reading_value_minor' => ['required', 'integer', 'min:0', 'max:99999999999999'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
