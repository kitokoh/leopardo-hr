<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réservation invitée marketplace VTC (#8423, M-13).
 *
 * Contrat `POST /public/vtc/marketplace/rides` — en-tête
 * **Idempotency-Key (UUID v4) OBLIGATOIRE** (anti-double-course, même
 * contrat que la création authentifiée `RideStoreRequest` : il est rabattu
 * dans le payload validé comme `idempotency_key`). Passager INVITÉ :
 * `passenger_name` + `passenger_phone` requis (aucun compte —
 * `passenger_user_id` reste null côté action, nullable depuis BC-35).
 * Coordonnées WGS 84 strictes.
 */
final class MarketplaceRideStoreRequest extends FormRequest
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
            'operator' => ['required', 'string', 'max:160'],
            'idempotency_key' => ['required', 'uuid'],
            'pickup' => ['required', 'array'],
            'pickup.lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng' => ['required', 'numeric', 'between:-180,180'],
            'pickup_address' => ['nullable', 'string', 'max:500'],
            'dropoff' => ['required', 'array'],
            'dropoff.lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['nullable', 'string', 'max:500'],
            'passenger_name' => ['required', 'string', 'max:120'],
            'passenger_phone' => ['required', 'string', 'max:40'],
        ];
    }
}
