<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;

/**
 * RESTO-603 (#6208) — Politique d'annulation des réservations.
 *
 * Configurable par branche (migration #6208) :
 *   - `cancel_free_hours` : délai (en heures) avant la réservation au-delà
 *     duquel l'annulation est gratuite (remboursement intégral du dépôt) ;
 *   - `cancel_fee_bps` : pénalité en points de base (1 bps = 0,01 %) appliquée
 *     sur le dépôt si l'annulation tombe dans le délai.
 *
 * Tous les calculs sont **serveur** (critère d'acceptation) : le client ne
 * transmet jamais le montant de pénalité.
 *
 * @return array{penalty_minor: int, refundable_minor: int, deposit_minor: int, fee_bps: int|null, free_hours: int|null}
 */
final class RestaurantCancellationPolicyService
{
    public function evaluate(RestaurantReservation $reservation): array
    {
        $deposit = (int) ($reservation->deposit_minor ?? 0);
        $branch = $reservation->branch;
        $freeHours = $branch?->cancel_free_hours !== null ? (int) $branch->cancel_free_hours : null;
        $feeBps = $branch?->cancel_fee_bps !== null ? (int) $branch->cancel_fee_bps : null;

        if ($deposit <= 0) {
            return [
                'penalty_minor' => 0,
                'refundable_minor' => 0,
                'deposit_minor' => $deposit,
                'fee_bps' => $feeBps,
                'free_hours' => $freeHours,
            ];
        }

        // Heures restantes avant la réservation (positives si dans le futur).
        $hoursBefore = (float) $reservation->reserved_at->diffInHours(now(), false);

        if ($freeHours !== null && $hoursBefore >= $freeHours) {
            // Annulation dans le délai de grâce : dépôt intégralement remboursé.
            return [
                'penalty_minor' => 0,
                'refundable_minor' => $deposit,
                'deposit_minor' => $deposit,
                'fee_bps' => $feeBps,
                'free_hours' => $freeHours,
            ];
        }

        $penalty = $feeBps !== null
            ? (int) round($deposit * $feeBps / 10000)
            : 0;

        return [
            'penalty_minor' => $penalty,
            'refundable_minor' => max(0, $deposit - $penalty),
            'deposit_minor' => $deposit,
            'fee_bps' => $feeBps,
            'free_hours' => $freeHours,
        ];
    }
}
