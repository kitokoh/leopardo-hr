<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Outbox\Application\Services\OutboxPublisher;
use App\Events\CompanyCreated;
use App\Events\SubscriptionPaid;

/**
 * MAT-008 (#5866) — Publication des événements de plateforme dans l'outbox
 * générique (BC-01 PLATFORM).
 *
 * Garantit qu'un pic de provisioning / paiement ne perd ni ne duplique un
 * événement : l'outbox déduplique par (event_type, idempotency_key) et le
 * dispatcher (`outbox:dispatch`) livre exactement une fois (lease).
 */
class PublishPlatformEventToOutbox
{
    public function __construct(
        private readonly OutboxPublisher $publisher,
    ) {}

    public function handleCompanyCreated(CompanyCreated $event): void
    {
        $this->publisher->publish(
            eventType: 'company.created',
            payload: [
                'company_id' => (string) $event->company->id,
                'company_name' => $event->company->name,
                'country' => $event->company->country,
                'occurred_at' => now()->toISOString(),
            ],
            companyId: (string) $event->company->id,
            idempotencyKey: 'company.created:'.(string) $event->company->id,
            aggregateType: 'company',
            aggregateId: (string) $event->company->id,
        );
    }

    public function handleSubscriptionPaid(SubscriptionPaid $event): void
    {
        $payment = $event->payment;

        $this->publisher->publish(
            eventType: 'billing.subscription_paid',
            payload: [
                'payment_id' => (string) $payment->id,
                'company_id' => (string) $payment->company_id,
                'amount' => (float) ($payment->amount ?? 0),
                'occurred_at' => now()->toISOString(),
            ],
            companyId: (string) $payment->company_id,
            idempotencyKey: 'billing.subscription_paid:'.(string) $payment->id,
            aggregateType: 'payment',
            aggregateId: (string) $payment->id,
        );
    }
}
