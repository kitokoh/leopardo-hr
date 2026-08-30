<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Enums\PromotionDiscountType;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use RuntimeException;

/**
 * RESTO-607 (#6212) — Promotions : validation serveur et application.
 *
 * Une promo n'est applicable que si elle est active, dans sa période, sous
 * son plafond d'utilisation et au-dessus du minimum de commande. Le cumul est
 * contrôlé : une seule promo par addition en v1 (règle explicite, spec §D8
 * « cumul contrôlé »). `validate` est sans effet de bord ; `apply`
 * incrémente `used_count` (appelé par le flux d'addition RESTO-405).
 */
final class RestaurantPromotionService
{
    /**
     * @return array{valid: bool, promo: RestaurantPromotion, discount_minor: int}
     */
    public function validateAndCompute(RestaurantPromotion $promo, int $orderTotalMinor, string $companyId): array
    {
        if ($promo->company_id !== $companyId || ! $promo->is_active) {
            throw new RuntimeException('Promotion inactive ou hors tenant.');
        }

        $now = now();

        if ($promo->starts_at !== null && $now->lt($promo->starts_at)) {
            throw new RuntimeException('Promotion pas encore active.');
        }

        if ($promo->ends_at !== null && $now->gt($promo->ends_at)) {
            throw new RuntimeException('Promotion expirée.');
        }

        if ($promo->max_uses !== null && (int) $promo->used_count >= (int) $promo->max_uses) {
            throw new RuntimeException('Promotion épuisée (nombre d\'utilisations maximal atteint).');
        }

        if ($promo->min_order_minor !== null && $orderTotalMinor < (int) $promo->min_order_minor) {
            throw new RuntimeException('Montant de commande sous le minimum requis.');
        }

        $discount = $promo->discount_type === PromotionDiscountType::PERCENT
            ? (int) round($orderTotalMinor * $promo->value_minor / 10000)
            : (int) $promo->value_minor;

        return [
            'valid' => true,
            'promo' => $promo,
            'discount_minor' => min($discount, $orderTotalMinor),
        ];
    }

    /**
     * Applique la promo (incrémente le compteur d'utilisations) — appelé par
     * le flux d'addition (RESTO-405) une seule fois par commande.
     */
    public function apply(RestaurantPromotion $promo): RestaurantPromotion
    {
        $promo->used_count = (int) $promo->used_count + 1;
        $promo->save();

        return $promo;
    }
}
