<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\InvoicePaid;
use App\Events\SubscriptionPaid;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Listeners\NotifyTaxRateValidation;
use App\Listeners\ProcessCommissionOnPayment;
use App\Listeners\SendInvoicePaymentReceipt;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * #8206 (BOS-017, critère 2) — les trois listeners à effet externe de la
 * liste fermée sont exécutés par la file : l'événement ne déclenche plus
 * AUCUN effet synchrone (email, PDF, écritures commission) dans le cycle de
 * la requête/webhook.
 */
class QueuedListenersTest extends TestCase
{
    public function test_the_three_listeners_implement_should_queue(): void
    {
        foreach ([SendInvoicePaymentReceipt::class, NotifyTaxRateValidation::class, ProcessCommissionOnPayment::class] as $listener) {
            $this->assertContains(
                ShouldQueue::class,
                class_implements($listener) ?: [],
                "{$listener} doit implémenter ShouldQueue.",
            );
        }
    }

    public function test_invoice_paid_queues_receipt_listener_and_sends_no_mail_synchronously(): void
    {
        Queue::fake();
        Mail::fake();

        InvoicePaid::dispatch(new Invoice);

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === SendInvoicePaymentReceipt::class
                && $job->method === 'handle',
        );
        Mail::assertNothingSent();
    }

    /**
     * #9757 — une facture payée produit EXACTEMENT UN reçu, jamais deux.
     *
     * Le listener pouvait être déclenché deux fois : une fois par l'entrée
     * `$listen` de `EventServiceProvider`, une fois par la découverte
     * automatique d'événements de Laravel 11 (forme `Class@handle`). Le
     * symptôme était un client qui recevait deux fois le même reçu PDF pour un
     * seul paiement.
     *
     * Ce test COMPTE les envois au lieu de vérifier « au moins un » : c'est le
     * seul moyen d'attraper une double inscription. `assertPushed` sans
     * prédicat de comptage ne prouve rien sur le doublon.
     */
    public function test_one_paid_invoice_dispatches_exactly_one_receipt(): void
    {
        Queue::fake();
        Mail::fake();

        InvoicePaid::dispatch(new Invoice);

        Queue::assertPushed(
            CallQueuedListener::class,
            1,
            fn (CallQueuedListener $job): bool => $job->class === SendInvoicePaymentReceipt::class,
        );
    }

    /**
     * #9757 — la re-transition idempotente `paid → paid` ne renvoie pas de reçu.
     *
     * C'est le cas des webhooks provider rejoués : Stripe peut réémettre
     * `invoice.paid` pour la même facture. Le modèle ne dispatche que sur une
     * transition RÉELLE (`$current !== $status`), donc un rejeu n'envoie rien —
     * sans quoi un client recevrait un reçu à chaque retry du provider.
     */
    public function test_replayed_paid_webhook_dispatches_no_additional_receipt(): void
    {
        Queue::fake();
        Mail::fake();

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->create([
            'company_id' => (string) \Illuminate\Support\Str::uuid(),
            'subscription_id' => null,
            'number' => 'INV-REPLAY-1',
            'period' => '2026-11',
            'amount' => 99.00,
            'tax_amount' => 0,
            'total' => 99.00,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid->value,
            'due_date' => '2026-12-01',
        ]);

        // Rejeu du webhook : la facture est DÉJÀ `paid`, la transition est
        // idempotente et ne doit produire aucun nouveau reçu.
        $invoice->transitionTo(InvoiceStatus::Paid);

        Queue::assertNotPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === SendInvoicePaymentReceipt::class,
        );
        Mail::assertNothingSent();
    }

    public function test_subscription_paid_queues_commission_listener(): void
    {
        Queue::fake();

        SubscriptionPaid::dispatch(new Payment);

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ProcessCommissionOnPayment::class,
        );
    }

    public function test_tax_rate_events_queue_notification_listener_with_their_method(): void
    {
        Queue::fake();

        TaxRateSubmitted::dispatch(new TaxSlab, new Employee);
        TaxRateApproved::dispatch(new TaxSlab, new SuperAdmin);
        TaxRateRejected::dispatch(new TaxSlab, new SuperAdmin, 'motif');

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === NotifyTaxRateValidation::class
                && $job->method === 'handleTaxRateSubmitted',
        );
        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === NotifyTaxRateValidation::class
                && $job->method === 'handleTaxRateApproved',
        );
        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === NotifyTaxRateValidation::class
                && $job->method === 'handleTaxRateRejected',
        );
    }
}
