<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Enums\LoyaltyPointsReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyPointsMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-606 (#6211) — Programme de fidélité.
 *
 * - Crédit de points à la commande **payée** : points = floor(total_minor /
 *   points_per_amount_minor), crédités **une seule fois par commande**
 *   (critère d'acceptation — idempotence par mouvement unique sur la
 *   commande, raison `order_paid`).
 * - Opt-in RGPD requis à l'activation du client fidélité.
 * - Solde jamais négatif : un débit au-delà du solde est refusé (422).
 */
final class RestaurantLoyaltyService
{
    /**
     * Crédite les points d'une commande payée sur un client fidélité.
     *
     * @return array{credited: int, customer: RestaurantLoyaltyCustomer, already_credited: bool}
     */
    public function creditForPaidOrder(RestaurantLoyaltyCustomer $customer, RestaurantOrder $order): array
    {
        if ($order->company_id !== $customer->company_id) {
            throw new RuntimeException('Commande et client fidélité ne sont pas dans le même tenant.');
        }

        if (! in_array($order->status->value, ['paid', 'closed'], true)) {
            throw new RuntimeException('La commande doit être payée pour créditer des points.');
        }

        $program = RestaurantLoyaltyProgram::query()
            ->where('company_id', $customer->company_id)
            ->where('is_active', true)
            ->first();

        if ($program === null || (int) $program->points_per_amount_minor <= 0) {
            throw new RuntimeException('Aucun programme de fidélité actif.');
        }

        $points = intdiv((int) $order->total_minor, (int) $program->points_per_amount_minor);

        if ($points <= 0) {
            return ['credited' => 0, 'customer' => $customer, 'already_credited' => true];
        }

        return DB::transaction(function () use ($customer, $order, $points): array {
            // Idempotence : une seule ligne de mouvement par (commande, raison).
            $existing = RestaurantLoyaltyPointsMovement::query()
                ->where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->where('reason_code', LoyaltyPointsReason::EARN->value)
                ->where('reference_id', $order->id)
                ->first();

            if ($existing !== null) {
                return ['credited' => 0, 'customer' => $customer, 'already_credited' => true];
            }

            RestaurantLoyaltyPointsMovement::query()->create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'delta' => $points,
                'reason_code' => LoyaltyPointsReason::EARN->value,
                'order_id' => $order->id,
                'reference_id' => $order->id,
            ]);

            $customer->points = (int) $customer->points + $points;
            $customer->save();

            return ['credited' => $points, 'customer' => $customer, 'already_credited' => false];
        });
    }

    /**
     * Débit/récompense : le solde ne passe jamais sous zéro.
     */
    public function redeem(RestaurantLoyaltyCustomer $customer, int $points, ?int $orderId = null): RestaurantLoyaltyCustomer
    {
        if ($points <= 0) {
            throw new RuntimeException('Le nombre de points à utiliser doit être positif.');
        }

        if ((int) $customer->points < $points) {
            throw new RuntimeException('Solde de points insuffisant.');
        }

        DB::transaction(function () use ($customer, $points, $orderId): void {
            RestaurantLoyaltyPointsMovement::query()->create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'delta' => -$points,
                'reason_code' => LoyaltyPointsReason::REDEEM->value,
                'order_id' => $orderId,
                'reference_id' => $orderId,
            ]);

            $customer->points = (int) $customer->points - $points;
            $customer->save();
        });

        return $customer;
    }
}
