<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->all();
        $type = $payload['type'] ?? '';

        Log::info('Stripe webhook received', ['type' => $type]);

        switch ($type) {
            case 'invoice.paid':
                $this->handleStripePaid($payload['data']['object'] ?? []);
                break;
            case 'invoice.payment_failed':
                $this->handleStripePaymentFailed($payload['data']['object'] ?? []);
                break;
            case 'customer.subscription.deleted':
                $this->handleStripeSubscriptionCancelled($payload['data']['object'] ?? []);
                break;
        }

        return response()->json(['received' => true]);
    }

    public function chargily(Request $request): JsonResponse
    {
        $payload = $request->all();
        $type = $payload['type'] ?? '';

        Log::info('Chargily webhook received', ['type' => $type]);

        if ($type === 'checkout.paid') {
            $checkout = $payload['data'] ?? [];
            $invoiceNumber = $checkout['metadata']['invoice_number'] ?? null;

            if ($invoiceNumber) {
                $invoice = Invoice::where('number', $invoiceNumber)->first();
                if ($invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_method' => 'chargily',
                    ]);

                    Payment::create([
                        'invoice_id' => $invoice->id,
                        'company_id' => $invoice->company_id,
                        'amount' => $invoice->total,
                        'currency' => $invoice->currency,
                        'method' => $checkout['payment_method'] ?? 'cib',
                        'provider_reference' => $checkout['id'] ?? null,
                        'status' => 'completed',
                        'paid_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $invoiceData
     */
    private function handleStripePaid(array $invoiceData): void
    {
        $stripeInvoiceId = $invoiceData['id'] ?? null;
        if (! $stripeInvoiceId) {
            return;
        }

        $invoice = Invoice::where('stripe_invoice_id', $stripeInvoiceId)->first();
        if ($invoice) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'stripe',
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'amount' => ($invoiceData['amount_paid'] ?? 0) / 100,
                'currency' => strtoupper($invoiceData['currency'] ?? 'eur'),
                'method' => 'card',
                'provider_reference' => $invoiceData['charge'] ?? null,
                'status' => 'completed',
                'paid_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $invoiceData
     */
    private function handleStripePaymentFailed(array $invoiceData): void
    {
        $stripeInvoiceId = $invoiceData['id'] ?? null;
        if (! $stripeInvoiceId) {
            return;
        }

        $invoice = Invoice::where('stripe_invoice_id', $stripeInvoiceId)->first();
        if ($invoice) {
            $invoice->update(['status' => 'overdue']);

            $subscription = $invoice->subscription;
            if ($subscription) {
                $subscription->update(['status' => 'past_due']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $subscriptionData
     */
    private function handleStripeSubscriptionCancelled(array $subscriptionData): void
    {
        $stripeSubId = $subscriptionData['id'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }
    }
}
