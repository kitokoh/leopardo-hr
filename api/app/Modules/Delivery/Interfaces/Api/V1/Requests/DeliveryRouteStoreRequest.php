<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une tournée (DELIVERY-202, issue #6286).
 *
 * `delivery_ids` : au moins un colis du tenant ; la ré-affectation d'un colis
 * déjà planifié est refusée par le service (409 DELIVERY_ALREADY_PLANNED).
 */
final class DeliveryRouteStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'route_date' => ['required', 'date'],
            'zone' => ['nullable', 'string', 'max:120'],
            'delivery_ids' => ['required', 'array', 'min:1', 'max:200'],
            'delivery_ids.*' => ['integer', 'distinct'],
        ];
    }
}
