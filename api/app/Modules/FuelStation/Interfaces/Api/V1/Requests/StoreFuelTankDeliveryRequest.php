<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'une livraison de carburant (FUEL-009, issue #5803).
 *
 * `quantity_minor` strictement positif (unités mineures) ; `external_id`
 * optionnel — s'il est fourni, l'enregistrement devient idempotent (unique
 * par tenant). `delivered_at` borné dans le passé proche pour éviter les
 * saisies futures erronées.
 */
class StoreFuelTankDeliveryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity_minor' => ['required', 'integer', 'min:1'],
            'unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'delivered_at' => ['nullable', 'date', 'before_or_equal:now'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
