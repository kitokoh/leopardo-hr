<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement de statut d'un arrêt (DELIVERY-203, issue #6287).
 *
 * `proof_document_id` (BC-20 par valeur) exigé pour `delivered` — refus
 * explicite 409 PROOF_REQUIRED côté service (machine à états).
 */
final class DeliveryStopStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:en_route,arrived,delivered,failed,skipped'],
            'proof_document_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
