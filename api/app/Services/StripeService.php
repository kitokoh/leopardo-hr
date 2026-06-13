<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stripe integration service for subscription management.
 *
 * Uses the Stripe REST API directly (no SDK dependency) to keep
 * the backend lean. Handles Checkout Sessions and Customer Portal.
 */
class StripeService
{
    private string $secretKey;

    private string $webhookSecret;

    /** @var array<string, string> plan => Stripe Price ID */
    private array $priceIds;

    public function __construct()
    {
        $this->secretKey = (string) config('services.stripe.secret');
        $this->webhookSecret = (string) config('services.stripe.webhook_secret');
        $this->priceIds = [
            'starter' => (string) config('services.stripe.price_starter'),
            'business' => (string) config('services.stripe.price_business'),
            'enterprise' => (string) config('services.stripe.price_enterprise'),
        ];
    }

    /**
     * Create a Stripe Checkout Session for a company to subscribe.
     *
     * @return array{url: string, session_id: string}
     */
    public function createCheckoutSession(Company $company, string $plan, string $successUrl, string $cancelUrl): array
    {
        $priceId = $this->priceIds[$plan] ?? null;
        if (!$priceId) {
            throw new InvalidArgumentException("Unknown plan: {$plan}");
        }

        $response = Http::withToken($this->secretKey, 'Bearer')
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'payment_method_types[]' => 'card',
                'line_items[0][price]' => $priceId,
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $company->id,
                'customer_email' => $company->email,
                'metadata[company_id]' => (string) $company->id,
                'metadata[company_name]' => $company->name,
                'metadata[plan]' => $plan,
                'subscription_data[metadata][company_id]' => (string) $company->id,
                'subscription_data[metadata][plan]' => $plan,
                'subscription_data[trial_period_days]' => 0,
            ]);

        if (!$response->successful()) {
            Log::error('Stripe: Failed to create checkout session', [
                'status' => $response->status(),
                'body' => $response->json(),
                'company_id' => $company->id,
            ]);
            throw new RuntimeException('Failed to create Stripe checkout session.');
        }

        $data = $response->json();

        return [
            'url' => $data['url'],
            'session_id' => $data['id'],
        ];
    }

    /**
     * Create a Stripe Customer Portal session for subscription management.
     */
    public function createPortalSession(string $stripeCustomerId, string $returnUrl): string
    {
        $response = Http::withToken($this->secretKey, 'Bearer')
            ->asForm()
            ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                'customer' => $stripeCustomerId,
                'return_url' => $returnUrl,
            ]);

        if (!$response->successful()) {
            Log::error('Stripe: Failed to create portal session', [
                'status' => $response->status(),
                'customer' => $stripeCustomerId,
            ]);
            throw new RuntimeException('Failed to create Stripe portal session.');
        }

        return $response->json('url');
    }

    /**
     * Verify and parse a Stripe webhook event.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    public function verifyWebhookSignature(string $payload, string $sigHeader): ?array
    {
        if (!$this->webhookSecret) {
            Log::warning('Stripe: Webhook secret not configured, skipping verification.');

            return json_decode($payload, true);
        }

        $elements = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$key, $value] = explode('=', trim($part), 2);
            $elements[$key] = $value;
        }

        $timestamp = $elements['t'] ?? null;
        $signature = $elements['v1'] ?? null;

        if (!$timestamp || !$signature) {
            return null;
        }

        // Reject events older than 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Stripe: Webhook timestamp too old', ['timestamp' => $timestamp]);

            return null;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Stripe: Webhook signature mismatch');

            return null;
        }

        return json_decode($payload, true);
    }

    /**
     * Handle a verified Stripe webhook event.
     */
    public function handleEvent(array $event): void
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($data),
            'invoice.paid' => $this->handleInvoicePaid($data),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($data),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($data),
            default => Log::info("Stripe: Unhandled event type: {$type}"),
        };
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $companyId = $session['metadata']['company_id'] ?? $session['client_reference_id'] ?? null;
        $plan = $session['metadata']['plan'] ?? 'starter';
        $subscriptionId = $session['subscription'] ?? null;
        $customerId = $session['customer'] ?? null;

        if (!$companyId) {
            Log::warning('Stripe: checkout.session.completed without company_id', $session);

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        $company = Company::query()->find($companyId);
        if (!$company) {
            Log::warning('Stripe: Company not found', ['company_id' => $companyId]);

            return;
        }

        // Update company status
        $company->update([
            'status' => 'active',
            'metadata' => array_merge($company->metadata ?? [], [
                'stripe_customer_id' => $customerId,
            ]),
        ]);

        // Create or update subscription
        Subscription::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'plan' => $plan,
                'status' => 'active',
                'payment_method' => 'stripe',
                'stripe_subscription_id' => $subscriptionId,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]
        );

        Log::info('Stripe: Subscription activated', [
            'company_id' => $companyId,
            'plan' => $plan,
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }

    private function handleInvoicePaid(array $invoice): void
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'active',
                'current_period_start' => isset($invoice['period_start'])
                    ? Carbon::createFromTimestamp($invoice['period_start'])
                    : now(),
                'current_period_end' => isset($invoice['period_end'])
                    ? Carbon::createFromTimestamp($invoice['period_end'])
                    : now()->addMonth(),
            ]);
        }
    }

    private function handleSubscriptionUpdated(array $subscription): void
    {
        $sub = Subscription::query()
            ->where('stripe_subscription_id', $subscription['id'] ?? '')
            ->first();

        if (!$sub) {
            return;
        }

        $status = match ($subscription['status'] ?? '') {
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled', 'cancelled' => 'cancelled',
            'unpaid' => 'unpaid',
            default => $sub->status,
        };

        $sub->update(['status' => $status]);
    }

    private function handleSubscriptionDeleted(array $subscription): void
    {
        $sub = Subscription::query()
            ->where('stripe_subscription_id', $subscription['id'] ?? '')
            ->first();

        if ($sub) {
            $sub->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Also mark company as inactive if desired
            if ($sub->company_id) {
                Company::query()
                    ->where('id', $sub->company_id)
                    ->update(['status' => 'suspended']);
            }

            Log::info('Stripe: Subscription cancelled', [
                'company_id' => $sub->company_id,
                'stripe_subscription_id' => $subscription['id'],
            ]);
        }
    }
}
