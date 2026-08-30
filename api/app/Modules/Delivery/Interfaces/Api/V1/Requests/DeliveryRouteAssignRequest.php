<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Affectation livreur/véhicule à une tournée (DELIVERY-202, issue #6286).
 */
final class DeliveryRouteAssignRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'min:1'],
            'vehicle_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
