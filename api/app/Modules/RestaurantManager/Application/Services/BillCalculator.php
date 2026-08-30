<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;

/**
 * RESTO-403 (#6190) / RESTO-405 (#6192) — Calcul serveur des totaux d'une
 * commande.
 *
 * Aucun montant n'est accepté depuis le client : sous-total, TVA, remise et
 * total sont TOUJOURS recalculés côté serveur en minor units entières.
 *
 * - sous-total = Σ lignes actives (line_total_minor, arrondi à l'ajout) ;
 * - taxe = Σ tax_minor des lignes (taux appliqué à l'ajout, rate_bps) ;
 * - remise = promotion validée serveur (bornée ≤ sous-total, fenêtre de
 *   validité, minimum de commande, plafond d'utilisations) ;
 * - total = sous-total + taxe − remise, jamais négatif.
 *
 * Ce service est la source de vérité unique des totaux de la verticale.
 */
final class BillCalculator
{
    /**
     * @return array{subtotal_minor: int, tax_minor: int, discount_minor: int, total_minor: int, currency: string}
     */
    public function calculate(RestaurantOrder $order, int $discountMinor = 0): array
    {
        $items = $order->items->filter(
            fn ($item) => $item->status === OrderItemStatus::ACTIVE
        );

        $subtotal = 0;
        $tax = 0;
        foreach ($items as $item) {
            $subtotal += (int) $item->line_total_minor;
            $tax += (int) ($item->tax_minor ?? 0);
        }

        // La remise ne peut jamais dépasser le sous-total (promos bornées).
        $discount = max(0, min($discountMinor, $subtotal));

        $total = max(0, $subtotal + $tax - $discount);

        return [
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'discount_minor' => $discount,
            'total_minor' => $total,
            'currency' => $order->currency,
        ];
    }

    /**
     * Calcul complet avec application éventuelle d'un code promotionnel.
     *
     * La promotion est résolue et validée côté serveur (tenant, branche,
     * fenêtre, minimum, plafond) ; son compteur d'utilisations est incrémenté
     * par l'appelant (contrôleur) dans la transaction de persistance des
     * totaux.
     *
     * @return array{subtotal_minor: int, tax_minor: int, discount_minor: int, total_minor: int, currency: string, promotion: RestaurantPromotion|null}
     */
    public function calculateWithPromotion(RestaurantOrder $order, ?string $promotionCode): array
    {
        $base = $this->calculate($order, 0);
        $promotion = null;

        if ($promotionCode !== null && $promotionCode !== '') {
            $promotion = $this->resolvePromotion($order, $promotionCode, $base['subtotal_minor']);
        }

        $discount = $promotion instanceof RestaurantPromotion
            ? $this->discountFor($promotion, $base['subtotal_minor'])
            : 0;

        $totals = $this->calculate($order, $discount);
        $totals['promotion'] = $promotion;

        return $totals;
    }

    private function resolvePromotion(RestaurantOrder $order, string $code, int $subtotal): RestaurantPromotion
    {
        $promotion = RestaurantPromotion::query()
            ->where('company_id', $order->company_id)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $promotion instanceof RestaurantPromotion) {
            abort(422, 'Unknown or inactive promotion code.');
        }

        if ($promotion->branch_id !== null && $promotion->branch_id !== $order->branch_id) {
            abort(422, 'Promotion is not valid for this branch.');
        }

        $now = now();
        if ($promotion->starts_at !== null && $promotion->starts_at->gt($now)) {
            abort(422, 'Promotion is not yet valid.');
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->lt($now)) {
            abort(422, 'Promotion has expired.');
        }

        if ($promotion->min_order_minor !== null && $subtotal < $promotion->min_order_minor) {
            abort(422, 'Order does not reach the promotion minimum.');
        }

        if ($promotion->max_uses !== null && $promotion->used_count >= $promotion->max_uses) {
            abort(422, 'Promotion usage limit reached.');
        }

        return $promotion;
    }

    /**
     * Remise serveur : pourcentage (rate_bps, ex. 1000 = 10 %) sur le
     * sous-total, ou montant fixe (minor units). Bornée ≤ sous-total.
     */
    private function discountFor(RestaurantPromotion $promotion, int $subtotal): int
    {
        $value = $promotion->discount_type === \App\Modules\RestaurantManager\Domain\Enums\PromotionDiscountType::PERCENT
            ? (int) round($subtotal * $promotion->value_minor / 10000)
            : (int) $promotion->value_minor;

        return max(0, min($value, $subtotal));
    }
}
