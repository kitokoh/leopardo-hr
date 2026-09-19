<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-902 (#7747) — Panier de la commande publique par slug.
 *
 * Route publique SANS auth (`POST /public/restaurants/{slug}/orders`) :
 * l'existence/publicité de la branche est tranchée fail-closed par
 * `RestaurantPublicBranchResolver` (404). Les produits sont identifiés par
 * leur `code` métier — l'identifiant exposé par le menu public (RESTO-805
 * boutique publique et menu du profil RESTO-901), jamais un ID interne.
 * Aucun montant n'est accepté du client (prix + TVA serveur,
 * CreateOnlineOrderAction). `order_type` accepte l'alias public `pickup`
 * (→ `takeaway` interne) en plus des valeurs OrderType supportées.
 */
class StoreRestaurantPublicSlugOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route publique : garde-fous fail-closed dans le contrôleur.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'order_type' => ['sometimes', 'string', Rule::in(['pickup', 'takeaway', 'delivery'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_code' => ['required', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999'],
            'items.*.note' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
