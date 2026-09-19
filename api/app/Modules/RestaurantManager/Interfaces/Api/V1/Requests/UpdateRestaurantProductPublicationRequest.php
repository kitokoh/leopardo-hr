<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-901 (#7746) — Publication en ligne d'un produit (menu public).
 *
 * L'autorisation est tranchée par `RestaurantProductPolicy::update()`
 * (gestion réservée aux gérants de la branche, pattern
 * ChecksRestaurantBranchAccess).
 */
class UpdateRestaurantProductPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantProductPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_published_online' => ['required', 'boolean'],
        ];
    }
}
