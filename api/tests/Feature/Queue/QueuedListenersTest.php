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
