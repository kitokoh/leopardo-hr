<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Enums\LoyaltyPointsReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-606 (#6211) — Crédit de points de fidélité à la commande payée.
 *
 * Règles (spec §4.5 / D8) :
 * - un seul programme actif par tenant ; taux `points_per_amount_minor` =
 *   tranche de dépense (minor units) qui rapporte 1 point ;
 * - opt-in RGPD : seuls les clients ayant un compte fidélité
 *   (`restaurant_loyalty_customers`) gagnent des points ;
 * - idempotence : l'unique partiel (company_id, customer_id, order_id) pour
 *   reason_code='earn' garantit « points crédités une seule fois par commande
 *   payée », même si le consommateur d'outbox est rejoué.
 */
final class CreditLoyaltyPointsAction
{
    public function creditForPaidOrder(RestaurantOrder $order): void
    {
        $program = RestaurantLoyaltyProgram::query()
            ->where('company_id', $order->company_id)
            ->where('is_active', true)
            ->first();

        if (! $program instanceof RestaurantLoyaltyProgram) {
            return; // programme inactif/absent → aucun crédit
        }

        if ($order->customer_contact_id === null) {
            return; // pas de client rattaché → pas de compte à créditer
        }

        $points = $this->pointsFor($program, (int) $order->total_minor);

        if ($points <= 0) {
            return;
        }

        $customer = RestaurantLoyaltyCustomer::query()
            ->where('company_id', $order->company_id)
            ->where('customer_contact_id', $order->customer_contact_id)
            ->first();

        if (! $customer instanceof RestaurantLoyaltyCustomer) {
            return; // opt-in requis : pas de compte fidélité → pas de crédit
        }

        $this->credit($customer, $order, $points);
    }

    private function pointsFor(RestaurantLoyaltyProgram $program, int $totalMinor): int
    {
        $rate = (int) $program->points_per_amount_minor;

        if ($rate <= 0) {
            return 0;
        }

        return intdiv($totalMinor, $rate);
    }

    private function credit(RestaurantLoyaltyCustomer $customer, RestaurantOrder $order, int $points): void
    {
        try {
            DB::transaction(function () use ($customer, $order, $points): void {
                $customer->increment('points', $points);

                $customer->movements()->create([
                    'company_id' => $customer->company_id,
                    'customer_id' => $customer->id,
                    'delta' => $points,
                    'reason_code' => LoyaltyPointsReason::EARN->value,
                    'order_id' => $order->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Rejeu : le crédit de cette commande existe déjà → idempotent.
        }
    }
}
