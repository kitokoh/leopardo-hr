<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\MarketReview;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Issue #7814 — Avis & notations MODÉRÉS de Leopardo Marché.
 *
 * Anti-abus (spec §6) :
 * - avis VÉRIFIÉ post-livraison : l'acheteur doit posséder une commande
 *   LIVRÉE (`fulfillment_status = delivered`) chez le vendeur — contenant le
 *   produit pour un avis produit (preuve d'achat, `order_id` par valeur) ;
 * - un avis par cible et par compte (unicité applicative + index partiels) ;
 * - modération : création en `pending`, seuls les avis `approved` sont
 *   publics (fail-closed), transition par le vendeur (MarketReviewPolicy).
 *
 * Toutes les lectures tenant lèvent explicitement le scope company
 * (`withoutGlobalScope('company')`) : la table des avis vit dans le schéma
 * public et les routes acheteur n'ont pas de tenant courant — chaque requête
 * est bornée par customer_account_id / company_id (jamais non bornée).
 */
final class MarketReviewService
{
    /**
     * Crée un avis VÉRIFIÉ en attente de modération.
     *
     * @throws ValidationException 422 REVIEW_NOT_ALLOWED (aucune commande
     *                             livrée éligible) | REVIEW_ALREADY_EXISTS.
     */
    public function submit(
        MarketCustomerAccount $account,
        string $companyId,
        string $targetType,
        ?int $productId,
        int $rating,
        ?string $comment,
    ): MarketReview {
        $order = $this->deliveredOrderFor($account, $companyId, $targetType === MarketReview::TYPE_PRODUCT ? $productId : null);

        if (! $order instanceof RetailOrder) {
            throw ValidationException::withMessages([
                'target' => 'REVIEW_NOT_ALLOWED',
            ]);
        }

        $duplicate = MarketReview::query()
            ->where('customer_account_id', $account->id)
            ->where('target_type', $targetType)
            ->when(
                $targetType === MarketReview::TYPE_PRODUCT,
                fn ($query) => $query->where('product_id', $productId),
                fn ($query) => $query->where('company_id', $companyId),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'target' => 'REVIEW_ALREADY_EXISTS',
            ]);
        }

        return MarketReview::query()->create([
            'customer_account_id' => $account->id,
            'company_id' => $companyId,
            'target_type' => $targetType,
            'product_id' => $targetType === MarketReview::TYPE_PRODUCT ? $productId : null,
            'order_id' => (int) $order->id,
            'rating' => $rating,
            'comment' => $comment,
            'status' => MarketReview::STATUS_PENDING,
        ]);
    }

    /**
     * Avis APPROUVÉS d'un produit public (fail-closed : jamais de pending
     * ni de rejected).
     *
     * @return LengthAwarePaginator<int, MarketReview>
     */
    public function approvedForProduct(int $productId, int $perPage = 20): LengthAwarePaginator
    {
        return MarketReview::query()
            ->where('target_type', MarketReview::TYPE_PRODUCT)
            ->where('product_id', $productId)
            ->where('status', MarketReview::STATUS_APPROVED)
            ->orderByDesc('moderated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Avis APPROUVÉS d'une boutique (avis boutique uniquement).
     *
     * @return LengthAwarePaginator<int, MarketReview>
     */
    public function approvedForSeller(string $companyId, int $perPage = 20): LengthAwarePaginator
    {
        return MarketReview::query()
            ->where('target_type', MarketReview::TYPE_SELLER)
            ->where('company_id', $companyId)
            ->where('status', MarketReview::STATUS_APPROVED)
            ->orderByDesc('moderated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Note moyenne publique (avis approuvés) par cible.
     *
     * @return array{average: float|null, count: int}
     */
    public function publicRating(string $targetType, string $companyId, ?int $productId): array
    {
        $query = MarketReview::query()
            ->where('target_type', $targetType)
            ->where('status', MarketReview::STATUS_APPROVED)
            ->when(
                $targetType === MarketReview::TYPE_PRODUCT,
                fn ($builder) => $builder->where('product_id', $productId),
                fn ($builder) => $builder->where('company_id', $companyId),
            );

        $count = (int) $query->count();
        $average = $count > 0 ? round((float) $query->avg('rating'), 2) : null;

        return ['average' => $average, 'count' => $count];
    }

    /**
     * Modération vendeur : `pending → approved|rejected` (transition
     * terminale, 422 INVALID_TRANSITION sinon).
     *
     * @throws ValidationException
     */
    public function moderate(MarketReview $review, string $status): MarketReview
    {
        if ($review->status !== MarketReview::STATUS_PENDING
            || ! in_array($status, [MarketReview::STATUS_APPROVED, MarketReview::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'INVALID_TRANSITION',
            ]);
        }

        $review->forceFill([
            'status' => $status,
            'moderated_at' => now(),
        ])->save();

        return $review;
    }

    /**
     * Commande LIVRÉE du compte chez ce vendeur — contenant le produit pour
     * un avis produit. Preuve d'achat de l'avis vérifié (spec §6).
     */
    private function deliveredOrderFor(MarketCustomerAccount $account, string $companyId, ?int $productId): ?RetailOrder
    {
        $query = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('customer_account_id', $account->id)
            ->where('fulfillment_status', RetailFulfillmentStatus::Delivered->value)
            ->orderByDesc('delivered_at');

        if ($productId !== null) {
            $query->whereIn('id', RetailOrderItem::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->select('order_id'));
        }

        /** @var RetailOrder|null $order */
        $order = $query->first();

        return $order;
    }
}
