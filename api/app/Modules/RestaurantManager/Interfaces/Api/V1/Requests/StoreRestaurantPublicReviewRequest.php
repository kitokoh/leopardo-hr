<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-902 (#7747) — Soumission d'un avis client public.
 *
 * Route publique SANS auth (`POST /public/restaurants/{slug}/reviews`,
 * throttle dédié strict) : la preuve d'achat est la `order_ref` (référence
 * `RST-…` non énumérable, remise au client à la commande) d'une commande de
 * CETTE branche dans un statut terminal servi/livré — vérifiée par le
 * contrôleur. Un seul avis par commande ; statut initial `pending`
 * (modération gérant avant publication).
 */
class StoreRestaurantPublicReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route publique : preuve d'achat vérifiée dans le contrôleur.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_ref' => ['required', 'string', 'max:40'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'author_name' => ['required', 'string', 'max:120'],
        ];
    }
}
