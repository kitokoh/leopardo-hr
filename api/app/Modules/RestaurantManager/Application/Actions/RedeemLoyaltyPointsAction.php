<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Enums\LoyaltyPointsReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-606 (#6211) — Échange de points fidélité (récompense).
 *
 * Règle « jamais négatif » (critère d'acceptation RESTO-606) : le solde ne
 * peut pas passer sous zéro — un échange au-delà du solde est refusé (422).
 * L'échange est tracé dans `restaurant_loyalty_points_movements`
 * (reason_code = redeem, delta négatif).
 */
final class RedeemLoyaltyPointsAction
{
    public function redeem(RestaurantLoyaltyCustomer $customer, int $points): void
    {
        if ($points <= 0) {
            abort(422, 'Points to redeem must be positive.');
        }

        if ($customer->points < $points) {
            abort(422, sprintf('Insufficient points: balance %d, requested %d.', $customer->points, $points));
        }

        DB::transaction(function () use ($customer, $points): void {
            $customer->decrement('points', $points);

            $customer->movements()->create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'delta' => -$points,
                'reason_code' => LoyaltyPointsReason::REDEEM->value,
            ]);
        });
    }
}
