<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-401 (#6188) — Validation stricte de clôture d'une session de caisse.
 *
 * `counted_cash_minor` est le comptage physique (minor units) ; un motif
 * `variance_reason` est exigé par l'action si l'écart est non nul.
 * L'autorisation est tranchée par `RestaurantPosSessionPolicy::close()`.
 */
class CloseRestaurantPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPosSessionPolicy::close() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_cash_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
            'variance_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
