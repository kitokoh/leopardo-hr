<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkout invite du marketplace public Retail (BC-17, #7808).
 *
 * Route PUBLIQUE (aucune auth, aucun contexte tenant) : la validation est
 * purement structurelle — l'existence du vendeur, des produits et leur
 * visibilite en ligne sont verifiees fail-closed par le controleur et
 * RetailOnlineOrderService (jamais de Rule::exists non borne cross-tenant).
 * Les prix ne sont JAMAIS acceptes du client : totaux serveur.
 */
class StoreRetailMarketOrderRequest extends FormRequest
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
            'seller' => ['required', 'string', 'max:160'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.phone' => ['required', 'string', 'max:40'],
            'customer.email' => ['nullable', 'string', 'email', 'max:160'],
            'delivery' => ['nullable', 'array'],
            'delivery.address' => ['nullable', 'string', 'max:255'],
            'delivery.city' => ['nullable', 'string', 'max:120'],
            'delivery.note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,3'],
        ];
    }
}
