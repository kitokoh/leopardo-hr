<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-805 (#6226) — commande en ligne publique.
 *
 * Le panier référence des `menu_item_id` (menu public) ; les prix, la TVA
 * et le total sont TOUJOURS recalculés serveur (jamais acceptés du client).
 * `consent` obligatoire (RGPD) ; quantité strictement positive (borne).
 */
class StoreRestaurantPublicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'order_type' => ['required', Rule::in(['takeaway', 'delivery'])],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'note' => ['nullable', 'string', 'max:500'],
            'consent' => ['required', 'accepted'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::in(['web', 'kiosk'])],
        ];
    }
}
