<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de course VTC (BC-34 VTC, VTC-03/#8359).
 *
 * Contrat `POST /v1/vtc/rides` — en-tête **Idempotency-Key (UUID v4)
 * OBLIGATOIRE** (anti-double-course, spec §7) : il est rabattu dans le
 * payload validé comme `idempotency_key`. Coordonnées WGS 84 strictes.
 */
final class RideStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');

        if (is_string($key) && $key !== '') {
            $this->merge(['idempotency_key' => $key]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'pickup' => ['required', 'array'],
            'pickup.lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng' => ['required', 'numeric', 'between:-180,180'],
            'pickup_address' => ['nullable', 'string', 'max:500'],
            'dropoff' => ['required', 'array'],
            'dropoff.lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['nullable', 'string', 'max:500'],
            'passenger_name' => ['nullable', 'string', 'max:120'],
            'passenger_phone' => ['nullable', 'string', 'max:40'],
            'fare_profile_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
