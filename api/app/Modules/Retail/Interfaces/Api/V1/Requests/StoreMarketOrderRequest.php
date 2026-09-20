<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkout invite de la marketplace Leopardo Marche (BC-17 RETAIL, #7808,
 * spec §3.2) — route PUBLIQUE, aucun utilisateur authentifie.
 *
 * 1 commande = 1 vendeur (`seller` = slug public, le panier multi-vendeurs
 * est scinde cote client). 1..50 lignes, quantite entiere 1..999. Les prix
 * ne sont JAMAIS acceptes du client (relus en base, totaux serveur —
 * RetailOnlineOrderService). Email optionnel (RGPD, donnees minimales),
 * paiement v1 = COD (`cash`), `idempotency_key` obligatoire (rejeu sans
 * doublon, cle unique par tenant). L'appartenance des produits au vendeur
 * et leur visibilite en ligne sont verifiees en transaction cote service
 * (fail-closed, pas de Rule::exists cross-tenant ici).
 *
 * Paiement en ligne (#7812) : `payment_method` accepte `cash` (COD, defaut
 * historique) ou `online` — dans ce cas un intent de paiement est cree
 * (RetailPaymentService) et la reponse embarque
 * `payment.{intent_reference,status,checkout_url}`.
 */
class StoreMarketOrderRequest extends FormRequest
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
            'seller' => ['required', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.phone' => ['required', 'string', 'max:40'],
            'customer.email' => ['nullable', 'email', 'max:160'],
            'delivery' => ['required', 'array'],
            'delivery.address' => ['required', 'string', 'max:255'],
            'delivery.city' => ['required', 'string', 'max:120'],
            'delivery.notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cash,online'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
