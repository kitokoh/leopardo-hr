<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\OrderStateMachine;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\RefundStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantRefund;
use App\Modules\RestaurantManager\Domain\Payments\RefundRequest;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-408 (#6195) — Remboursement d'une commande (réservé
 * `restaurant.manage`, critère d'acceptation).
 *
 * Invariants :
 * - montant remboursable = Σ paiements confirmés − Σ remboursements déjà
 *   émis (double remboursement impossible : montant > restant → 422) ;
 * - idempotence par `idempotency_key` (rejeu → même remboursement) ;
 * - le remboursement passe par la passerelle du paiement d'origine (ou
 *   espèces par défaut) ; `processed` immédiat pour cash/carte ;
 * - la commande passe `paid → refunded` (machine à états) si intégralement
 *   remboursée ; événement `restaurant.payment.refunded.v1` publié.
 */
final class RefundOrderAction
{
    public const EVENT_PAYMENT_REFUNDED = 'restaurant.payment.refunded.v1';

    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly OrderStateMachine $stateMachine,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    /**
     * @param  array{amount_minor: int, reason_code: string, reason_text?: string|null, payment_id?: int|null, idempotency_key?: string|null}  $data
     */
    public function refund(Employee $actor, RestaurantOrder $order, array $data): RestaurantRefund
    {
        if ($order->company_id !== $actor->company_id) {
            throw new RuntimeException('Order does not belong to tenant.');
        }

        if ($order->status !== OrderStatus::PAID) {
            abort(409, sprintf('Only a paid order can be refunded (current status "%s").', $order->status->value));
        }

        if (isset($data['idempotency_key'])) {
            $existing = RestaurantRefund::query()
                ->where('company_id', $order->company_id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof RestaurantRefund) {
                return $existing;
            }
        }

        $payment = null;
        $providerCode = 'cash';

        if (! empty($data['payment_id'])) {
            /** @var RestaurantOrderPayment|null $payment */
            $payment = RestaurantOrderPayment::query()
                ->where('company_id', $order->company_id)
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::CONFIRMED->value)
                ->find($data['payment_id']);

            if (! $payment instanceof RestaurantOrderPayment) {
                abort(422, 'Payment not found, not confirmed, or not attached to this order.');
            }

            $providerCode = $payment->provider_code->value;
        }

        $refundable = $this->refundableAmount($order);

        if ($data['amount_minor'] <= 0 || $data['amount_minor'] > $refundable) {
            abort(422, sprintf('Refund amount must be between 1 and %d (remaining refundable).', $refundable));
        }

        $gateway = $this->registry->resolve($providerCode);

        $refund = DB::transaction(function () use ($actor, $order, $payment, $data): RestaurantRefund {
            // Re-vérification sous transaction (course entre deux refunds).
            if ($data['amount_minor'] > $this->refundableAmount($order)) {
                abort(422, 'Refundable amount changed concurrently; reload and retry.');
            }

            return RestaurantRefund::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'payment_id' => $payment?->id,
                'amount_minor' => $data['amount_minor'],
                'reason_code' => $data['reason_code'],
                'reason_text_redacted' => $data['reason_text'] ?? null,
                'refunded_by_user_id' => $actor->id,
                'status' => RefundStatus::PENDING->value,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
        });

        // Appel passerelle hors transaction (peut être distant).
        $gatewayRefund = $gateway->refund(new RefundRequest(
            companyId: $order->company_id,
            providerReference: (string) ($payment?->provider_reference ?? ''),
            amountMinor: $refund->amount_minor,
            currency: $order->currency,
            reasonCode: $refund->reason_code,
        ));

        if ($gatewayRefund->status === PaymentStatus::REFUNDED) {
            $refund->forceFill(['status' => RefundStatus::PROCESSED->value])->save();

            if ($payment !== null) {
                $payment->forceFill(['status' => PaymentStatus::REFUNDED->value])->save();
            }

            if ($this->refundableAmount($order) <= 0) {
                $this->markOrderRefunded($order);
            }
        }

        $this->outbox->publish(
            $order->company_id,
            self::EVENT_PAYMENT_REFUNDED,
            [
                'refund_id' => $refund->id,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount_minor' => $refund->amount_minor,
                'currency' => $order->currency,
                'reason_code' => $refund->reason_code,
            ],
        );

        $refund->refresh();

        return $refund;
    }

    /**
     * Montant encore remboursable : Σ paiements confirmés − Σ remboursements
     * (pending + processed).
     */
    public function refundableAmount(RestaurantOrder $order): int
    {
        $confirmed = (int) RestaurantOrderPayment::query()
            ->where('company_id', $order->company_id)
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->sum('amount_minor');

        $refunded = (int) RestaurantRefund::query()
            ->where('company_id', $order->company_id)
            ->where('order_id', $order->id)
            ->whereIn('status', [RefundStatus::PENDING->value, RefundStatus::PROCESSED->value])
            ->sum('amount_minor');

        return $confirmed - $refunded;
    }

    private function markOrderRefunded(RestaurantOrder $order): void
    {
        if (! $this->stateMachine->canTransition($order->status, OrderStatus::REFUNDED)) {
            return;
        }

        DB::table('restaurant_orders')
            ->where('id', $order->id)
            ->where('company_id', $order->company_id)
            ->where('version', $order->version)
            ->update([
                'status' => OrderStatus::REFUNDED->value,
                'version' => $order->version + 1,
            ]);

        $order->refresh();
    }
}
