<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FUEL-004 — revue d'un intervalle en anomalie (accept/reject).
 */
class ReviewIntervalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC réel dans FuelMeterReadingPolicy::review.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['accept', 'reject'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
