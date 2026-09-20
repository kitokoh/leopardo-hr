<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\MarketplaceReviewStatus;
use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use App\Modules\Retail\Domain\Models\MarketplaceReview;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use Illuminate\Validation\ValidationException;

/**
 * Avis verifies de la marketplace Leopardo Marche (BC-17 RETAIL, #7814).
 *
 * Un avis (note 1..5 + commentaire optionnel) n'est accepte que si :
 *  - la commande referencee APPARTIENT au buyer authentifie (buyer_id) ;
 *  - la commande est `delivered` (avis verifie post-livraison) ;
 *  - la commande CONTIENT le produit note ;
 *  - aucun avis n'existe deja pour (buyer, commande, produit) — anti-abus,
 *    double garantie par l'index unique en base.
 *
 * v1 : auto-approve (`approved`) — le champ statut est present pour la
 * moderation v2. Refus uniformes en 422 (codes stables) ou 404 fail-closed.
 */
final class RetailBuyerReviewService
{
    /**
     * @throws ValidationException 422 ORDER_NOT_DELIVERED | PRODUCT_NOT_IN_ORDER | ALREADY_REVIEWED
     */
    public function submit(
        MarketplaceBuyer $buyer,
        string $orderReference,
        int $productId,
        int $rating,
        ?string $comment,
    ): MarketplaceReview {
        /** @var RetailOrder|null $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('buyer_id', (int) $buyer->id)
            ->where('reference', $orderReference)
            ->where('source', 'online')
            ->first();

        if (! $order instanceof RetailOrder) {
            // Reference inconnue OU commande d'un autre buyer : 404 uniforme
            // (pas de probing des references).
            abort(404);
        }

        if ($order->fulfillment_status !== RetailFulfillmentStatus::Delivered) {
            throw ValidationException::withMessages([
                'order_reference' => 'ORDER_NOT_DELIVERED',
            ]);
        }

        $containsProduct = RetailOrderItem::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->where('product_id', $productId)
            ->exists();

        if (! $containsProduct) {
            throw ValidationException::withMessages([
                'product_id' => 'PRODUCT_NOT_IN_ORDER',
            ]);
        }

        $already = MarketplaceReview::query()
            ->where('buyer_id', (int) $buyer->id)
            ->where('order_id', (int) $order->id)
            ->where('product_id', $productId)
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'product_id' => 'ALREADY_REVIEWED',
            ]);
        }

        return MarketplaceReview::query()->create([
            'buyer_id' => (int) $buyer->id,
            'company_id' => (string) $order->company_id,
            'product_id' => $productId,
            'order_id' => (int) $order->id,
            'rating' => $rating,
            'comment' => $comment,
            // v1 : auto-approve — la moderation (pending/rejected) est
            // prevue v2, le champ existe deja.
            'status' => MarketplaceReviewStatus::Approved->value,
        ]);
    }
}
