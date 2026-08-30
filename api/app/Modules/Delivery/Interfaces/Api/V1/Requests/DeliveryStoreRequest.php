<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une livraison (DELIVERY-201, issue #6285).
 *
 * Validation stricte : source connue, source_reference obligatoire hors
 * `manual`, montants >= 0 (minor units), adresses requises. La référence
 * `DLV-…` est générée côté serveur (jamais fournie par le client).
 */
final class DeliveryStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'in:manual,restaurant,retail,ecommerce,crm,field'],
            'source_reference' => ['nullable', 'string', 'max:120', 'required_if:source,restaurant,retail,ecommerce,crm,field'],
            'type' => ['required', 'string', 'in:parcel,order,food,grocery,medication,document'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'volume_cm3' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'declared_value_minor' => ['nullable', 'integer', 'min:0'],
            'cod_amount_minor' => ['nullable', 'integer', 'min:0'],
            'pickup_contact' => ['nullable', 'string', 'max:150'],
            'pickup_address' => ['nullable', 'string', 'max:2000'],
            'dropoff_contact' => ['required', 'string', 'max:150'],
            'dropoff_phone' => ['nullable', 'string', 'max:40'],
            'dropoff_address' => ['required', 'string', 'max:2000'],
            'window_from' => ['nullable', 'date'],
            'window_to' => ['nullable', 'date', 'after_or_equal:window_from'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
