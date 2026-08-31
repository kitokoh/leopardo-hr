<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\OrderStateMachine;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-407 (#6194) — Encaissement d'une commande.
 *
 * Invariants :
 * - montant vérifié serveur : `amount_minor` doit égaler le reste à payer
 *   (total − Σ paiements confirmés) — aucun montant client accepté tel quel ;
 * - idempotence : rejeu d'une même `idempotency_key` → même paiement ;
 * - double paiement impossible : commande déjà intégralement réglée → 409 ;
 * - le fournisseur est résolu via PaymentGatewayRegistry (cash/card
 *   confirmés immédiatement, mobile_money pending → callback signé) ;
 * - événements outbox : `restaurant.payment.confirmed.v1` à chaque paiement
 *   confirmé, `restaurant.order.paid.v1` quand la commande est soldée.
 */
final class PayOrderAction
{
    public const EVENT_PAYMENT_CONFIRMED = 'restaurant.payment.confirmed.v1';

    public const EVENT_ORDER_PAID = 'restaurant.order.paid.v1';

    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly OrderStateMachine $stateMachine,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    /**
     * @param  array{provider_code: string, amount_minor: int, tip_minor?: int|null, idempotency_key?: string|null}  $data
     */
    public function pay(Employee $actor, RestaurantOrder $order, array $data): RestaurantOrderPayment
    {
        if ($order->company_id !== $actor->company_id) {
            throw new RuntimeException('Order does not belong to tenant.');
        }

        if (! $this->stateMachine->isPayable($order->status)) {
            abort(409, sprintf('Order cannot be paid from status "%s".', $order->status->value));
        }

        if (isset($data['idempotency_key'])) {
            $existing = RestaurantOrderPayment::query()
                ->where('company_id', $order->company_id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof RestaurantOrderPayment) {
                return $existing;
            }
        }

        $remaining = $this->remainingDue($order);

        if ($remaining <= 0) {
            abort(409, 'Order is already fully paid.');
        }

        if ($data['amount_minor'] !== $remaining) {
            abort(422, sprintf('Amount mismatch: expected %d, got %d.', $remaining, $data['amount_minor']));
        }

        $gateway = $this->registry->resolve($data['provider_code']);

        $payment = DB::transaction(function () use ($order, $data, $remaining): RestaurantOrderPayment {
            // Re-vérification sous transaction : le montant restant est
            // recalculé pour fermer la course entre deux encaissements.
            $currentRemaining = $this->remainingDue($order);
            if ($currentRemaining !== $remaining) {
                abort(409, 'Order balance changed concurrently; reload and retry.');
            }

            return RestaurantOrderPayment::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'pos_session_id' => $order->pos_session_id,
                'provider_code' => $data['provider_code'],
                'amount_minor' => $remaining,
                'currency' => $order->currency,
                'status' => PaymentStatus::PENDING->value,
                'tip_minor' => $data['tip_minor'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
        });

        // Initiation hors transaction : la passerelle peut être lente /
        // distante (mobile money). En cash/carte la confirmation est
        // immédiate ; en mobile money le paiement reste pending en attendant
        // le callback signé.
        $init = $gateway->initiate(new InitiatePaymentRequest(
            companyId: $order->company_id,
            amountMinor: $payment->amount_minor,
            currency: $order->currency,
            reference: $order->reference,
            idempotencyKey: (string) $payment->idempotency_key,
        ));

        $payment->forceFill([
            'provider_reference' => $init->providerReference,
        ])->save();

        if ($init->status === PaymentStatus::CONFIRMED) {
            $this->confirmPayment($payment, $init->providerReference);
        }

        $payment->refresh();

        return $payment;
    }

    /**
     * Confirme un paiement (cash/carte immédiat, mobile money via callback)
     * et solde la commande si nécessaire. Publie les événements outbox après
     * commit.
     */
    public function confirmPayment(RestaurantOrderPayment $payment, ?string $providerReference): void
    {
        $payment = DB::transaction(function () use ($payment, $providerReference): RestaurantOrderPayment {
            if ($payment->status === PaymentStatus::CONFIRMED) {
                return $payment; // rejeu idempotent
            }

            $payment->forceFill([
                'status' => PaymentStatus::CONFIRMED->value,
                'paid_at' => now(),
                'provider_reference' => $providerReference ?? $payment->provider_reference,
            ])->save();

            /** @var RestaurantOrder $order */
            $order = $payment->order()->first();

            if ($order !== null && $this->remainingDue($order) <= 0) {
                $this->markOrderPaid($order);
            }

            return $payment;
        });

        $this->outbox->publish(
            $payment->company_id,
            self::EVENT_PAYMENT_CONFIRMED,
            [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'provider_code' => $payment->provider_code->value,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
            ],
        );

        $order = $payment->order()->first();
        if ($order !== null && $order->status === OrderStatus::PAID) {
            $this->outbox->publish(
                $order->company_id,
                self::EVENT_ORDER_PAID,
                [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'branch_id' => $order->branch_id,
                    'total_minor' => $order->total_minor,
                    'currency' => $order->currency,
                ],
            );
        }
    }

    /**
     * Reste à payer : total − Σ paiements confirmés (minor units).
     */
    public function remainingDue(RestaurantOrder $order): int
    {
        $confirmed = RestaurantOrderPayment::query()
            ->where('company_id', $order->company_id)
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->sum('amount_minor');

        return (int) $order->total_minor - (int) $confirmed;
    }

    /**
     * Passe la commande à `paid` (verrou optimiste `version`) si la machine à
     * états l'autorise.
     */
    private function markOrderPaid(RestaurantOrder $order): void
    {
        if (! $this->stateMachine->canTransition($order->status, OrderStatus::PAID)) {
            return;
        }

        DB::table('restaurant_orders')
            ->where('id', $order->id)
            ->where('company_id', $order->company_id)
            ->where('version', $order->version)
            ->update([
                'status' => OrderStatus::PAID->value,
                'version' => $order->version + 1,
            ]);

        $order->refresh();
    }
}
