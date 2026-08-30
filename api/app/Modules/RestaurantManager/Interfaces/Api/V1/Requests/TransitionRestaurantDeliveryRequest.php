<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-605 (#6210) — Validation d'une transition de livraison.
 *
 * L'action cible est portée par l'URL (assign / out-for-delivery / deliver /
 * cancel) et traduite en statut par le contrôleur — jamais acceptée du
 * client. `rider_id` est requis pour `assign` (validé plus finement dans
 * TransitionDeliveryAction : tenant, branche, livreur actif).
 */
class TransitionRestaurantDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryPolicy::transition() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rider_id' => ['nullable', 'integer'],
            'delivered_to_contact' => ['nullable', 'string', 'max:150'],
        ];
    }
}
