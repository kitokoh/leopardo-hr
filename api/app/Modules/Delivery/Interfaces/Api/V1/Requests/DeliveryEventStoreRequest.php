<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'un événement de tracking (DELIVERY-204, issue #6288).
 *
 * `proof_document_id` (BC-20 par valeur) est exigé pour `delivered` — refus
 * explicite 409 PROOF_REQUIRED côté service (l'état de la POD se décide dans
 * la machine à états, pas dans la validation HTTP).
 */
final class DeliveryEventStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:picked_up,out_for_delivery,arrived,delivered,failed,returned'],
            'event_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'origin' => ['nullable', 'string', 'in:mobile,edge,api'],
            'idempotency_key' => ['nullable', 'uuid'],
            'proof_document_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
