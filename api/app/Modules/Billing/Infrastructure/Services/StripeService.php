<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Events\SubscriptionPaid;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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

    public function __construct(?PaymentGatewayConfigProviderInterface $gatewayConfig = null)
    {
        // #7726 : la configuration passe par GatewaySettingsService —
        // précédence BDD (admin plateforme, secrets chiffrés) → fallback env
        // (comportement historique inchangé sans ligne BDD). Paramètre
        // optionnel : les appels historiques `new StripeService` restent
        // valides (résolution container par défaut).
        $gatewayConfig ??= app(PaymentGatewayConfigProviderInterface::class);
        $settings = $gatewayConfig->resolve('stripe');

        $this->secretKey = $settings['secret_key'] ?? '';
        $this->webhookSecret = $settings['webhook_secret'] ?? '';
        $this->priceIds = [
            'pilot' => $settings['price_pilot'] ?? '',
            'operations' => $settings['price_operations'] ?? '',
            'enterprise' => $settings['price_enterprise'] ?? '',
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
        if (! $priceId) {
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

        if (! $response->successful()) {
            Log::error('Stripe: Failed to create checkout session', [
                'status' => $response->status(),
                'body' => $response->json(),
                'company_id' => $company->id,
            ]);
            throw new RuntimeException('Failed to create Stripe checkout session.');
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['url'], $data['id'])) {
            Log::error('Stripe: Réponse checkout invalide', [
                'status' => $response->status(),
                'company_id' => $company->id,
            ]);
            throw new RuntimeException('Invalid Stripe checkout response.');
        }

        return [
            'url' => strval($data['url']),
            'session_id' => strval($data['id']),
        ];
    }

    /**
     * Create a Stripe Checkout Session `mode=payment` (one-shot) for an AI
     * credit pack purchase (#7764).
     *
     * Pas de Price pré-créé côté Stripe : les packs sont des constantes
     * versionnées (`AiCreditService::PACKS`), le prix part en `price_data`
     * inline. Les metadata `{purpose, company_id, pack, tokens}` permettent au
     * webhook `checkout.session.completed` (mode payment) de créditer le
     * ledger — idempotent via le registre #5444 ET la référence unique
     * (id de session) du ledger.
     *
     * @return array{url: string, session_id: string}
     */
    public function createCreditCheckoutSession(
        Company $company,
        string $pack,
        int $tokens,
        int $amountCents,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $response = Http::withToken($this->secretKey, 'Bearer')
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => 'eur',
                'line_items[0][price_data][unit_amount]' => $amountCents,
                'line_items[0][price_data][product_data][name]' => sprintf(
                    'Leopardo — Crédits IA pack %s (%s tokens)',
                    strtoupper($pack),
                    number_format($tokens, 0, ',', ' '),
                ),
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $company->id,
                'customer_email' => $company->email,
                'metadata[purpose]' => 'ai_credits',
                'metadata[company_id]' => (string) $company->id,
                'metadata[pack]' => $pack,
                'metadata[tokens]' => (string) $tokens,
            ]);

        if (! $response->successful()) {
            Log::error('Stripe: Failed to create AI credit checkout session', [
                'status' => $response->status(),
                'body' => $response->json(),
                'company_id' => $company->id,
                'pack' => $pack,
            ]);
            throw new RuntimeException('Failed to create Stripe AI credit checkout session.');
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['url'], $data['id'])) {
            Log::error('Stripe: Réponse checkout crédits IA invalide', [
                'status' => $response->status(),
                'company_id' => $company->id,
            ]);
            throw new RuntimeException('Invalid Stripe AI credit checkout response.');
        }

        return [
            'url' => strval($data['url']),
            'session_id' => strval($data['id']),
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

        if (! $response->successful()) {
            Log::error('Stripe: Failed to create portal session', [
                'status' => $response->status(),
                'customer' => $stripeCustomerId,
            ]);
            throw new RuntimeException('Failed to create portal session.');
        }

        return $response->json('url');
    }

    /**
     * Verify and parse a Stripe webhook event.
     *
     * @return array{id?: string, type: string, data: array<string, mixed>}|null
     */
    public function verifyWebhookSignature(string $payload, string $sigHeader): ?array
    {
        if (! $this->webhookSecret) {
            // #2614 fail-closed : secret absent = webhook non vérifiable = on
            // REJETTE (null). Un secret vide ne doit jamais accepter un
            // payload (fail-open = signature by-passable en prod).
            Log::error('Stripe: Webhook secret not configured — webhook REJETÉ (fail-closed).');

            return null;
        }

        $elements = [];
        foreach (explode(',', $sigHeader) as $part) {
            $parts = explode('=', trim($part), 2);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                // En-tête malformé : rejet fail-closed, sans laisser remonter
                // une exception de parsing vers le endpoint public.
                Log::warning('Stripe: Malformed webhook signature header');

                return null;
            }

            $elements[$parts[0]] = $parts[1];
        }

        $timestamp = $elements['t'] ?? null;
        $signature = $elements['v1'] ?? null;

        if (! $timestamp || ! $signature) {
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
            'invoice.created' => $this->handleInvoiceCreated($data),
            'invoice.paid' => $this->handleInvoicePaid($data),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($data),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($data),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($data),
            'charge.refunded' => $this->handleChargeRefunded($data),
            default => Log::info("Stripe: Unhandled event type: {$type}"),
        };
    }

    private function handleCheckoutCompleted(array $session): void
    {
        // #7764 — checkout one-shot (mode=payment) d'un pack de crédits IA :
        // aucune souscription à activer, on crédite le ledger et on sort.
        $creditMetadata = $session['metadata'] ?? null;
        if (($session['mode'] ?? null) === 'payment'
            && is_array($creditMetadata)
            && ($creditMetadata['purpose'] ?? null) === 'ai_credits') {
            $this->handleAiCreditCheckoutCompleted($session);

            return;
        }

        $companyId = $session['metadata']['company_id'] ?? $session['client_reference_id'] ?? null;
        $plan = PlanCode::normalize((string) ($session['metadata']['plan'] ?? PlanCode::Pilot->value))->value;
        $subscriptionId = $session['subscription'] ?? null;
        $customerId = $session['customer'] ?? null;

        if (! $companyId) {
            Log::warning('Stripe: checkout.session.completed without company_id', $session);

            return;
        }

        // Lecture QUALIFIÉE de `public.companies` (DEP-BC21 #6246) : l'ancien
        // `SET search_path TO public` cassait les écritures suivantes sur la
        // session — `subscriptions` vit dans le schéma tenant (shared_tenants
        // en mode shared), l'écriture partait sur `public.subscriptions` qui
        // n'existe pas (webhook checkout → 500 → retries Stripe). On lit le
        // modèle depuis sa table qualifiée SANS détourner le search_path.
        $company = Company::query()
            ->from(DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies')
            ->find($companyId);
        if (! $company) {
            Log::warning('Stripe: Company not found', ['company_id' => $companyId]);

            return;
        }
        assert($company instanceof \App\Core\Tenant\Domain\Models\Company);

        // Update company status
        $company->update([
            'status' => 'active',
            'metadata' => array_merge($company->metadata ?? [], [
                'stripe_customer_id' => $customerId,
            ]),
        ]);

        // Create or activate the subscription (DEP-BC21 #6246) : la création
        // pose l'état actif initial ; une souscription existante (trial,
        // past_due, expired, cancelled) est réactivée via la transition
        // gardée — un webhook rejoué retombe sur l'état actif sans effet double.
        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->first();

        $attributes = [
            'plan' => $plan,
            'payment_method' => 'stripe',
            'stripe_subscription_id' => $subscriptionId,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];

        if (! $subscription) {
            Subscription::query()->create([
                'company_id' => $company->id,
                'status' => SubscriptionStatus::Active->value,
                ...$attributes,
            ]);
        } else {
            $this->transitionSubscription($subscription, SubscriptionStatus::Active, $attributes, allowReactivation: true);
        }

        Log::info('Stripe: Subscription activated', [
            'company_id' => $companyId,
            'plan' => $plan,
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * #7764 — crédite le ledger après un achat one-shot de crédits IA.
     *
     * Idempotence à DEUX niveaux : le registre webhook (#5444, rejeu d'événement
     * Stripe) ET la référence unique du ledger (id de session — couvre aussi un
     * événement DIFFÉRENT portant la même session). Un payload sans metadata
     * exploitables est journalisé et ignoré (jamais d'exception → pas de retry
     * inutile côté Stripe pour un payload définitivement invalide).
     *
     * @param  array<string, mixed>  $session
     */
    private function handleAiCreditCheckoutCompleted(array $session): void
    {
        $metadata = $session['metadata'] ?? [];
        if (! is_array($metadata)) {
            $metadata = [];
        }

        $companyId = $metadata['company_id'] ?? $session['client_reference_id'] ?? null;
        $pack = strval($metadata['pack'] ?? '');
        $tokens = (int) ($metadata['tokens'] ?? 0);
        $sessionId = strval($session['id'] ?? '');

        if (! $companyId || $tokens <= 0 || $sessionId === '') {
            Log::warning('Stripe: checkout crédits IA sans metadata exploitables — ignoré', [
                'session_id' => $sessionId,
                'company_id' => $companyId,
                'tokens' => $tokens,
            ]);

            return;
        }

        $credited = app(AiCreditService::class)->credit(strval($companyId), $tokens, $sessionId);

        Log::info('Stripe: achat de crédits IA traité', [
            'company_id' => $companyId,
            'pack' => $pack,
            'tokens' => $tokens,
            'session_id' => $sessionId,
            'credited' => $credited,
        ]);
    }

    /**
     * Rapprochement Stripe (#7763) — `invoice.created` : écrire
     * `stripe_invoice_id` sur la facture interne du tenant (retrouvée par
     * souscription + période). Aucun changement d'état ici : la facture
     * interne suit sa propre machine à états (l'encaissement arrive via
     * `invoice.paid`). Idempotent : un événement rejoué retombe sur la
     * facture déjà rapprochée (lookup par stripe_invoice_id en premier),
     * en plus du registre #5444 au niveau du contrôleur.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function handleInvoiceCreated(array $invoice): void
    {
        $this->reconcileStripeInvoice($invoice);
    }

    /**
     * Retrouve la facture interne correspondant à un objet `invoice` Stripe.
     *
     * 1. Par `stripe_invoice_id` (facture déjà rapprochée) — comportement
     *    historique de handleInvoicePaid/handleInvoicePaymentFailed, inchangé.
     * 2. Sinon par souscription (`stripe_subscription_id`) + période
     *    (`period_start` → `Y-m`, le format de `invoices.period` posé par
     *    GenerateMonthlyInvoices) : la facture interne encore non rapprochée
     *    reçoit alors son `stripe_invoice_id` (#7763).
     *
     * @param  array<string, mixed>  $invoice
     */
    private function reconcileStripeInvoice(array $invoice): ?Invoice
    {
        $stripeInvoiceId = is_string($invoice['id'] ?? null) ? $invoice['id'] : '';

        $invoiceModel = Invoice::query()
            ->where('stripe_invoice_id', $stripeInvoiceId)
            ->first();

        if ($invoiceModel) {
            return $invoiceModel;
        }

        if ($stripeInvoiceId === '') {
            return null;
        }

        $subscriptionId = is_string($invoice['subscription'] ?? null) ? $invoice['subscription'] : null;
        if ($subscriptionId === null || $subscriptionId === '') {
            return null;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription) {
            return null;
        }

        $period = isset($invoice['period_start']) && is_numeric($invoice['period_start'])
            ? Carbon::createFromTimestamp((int) $invoice['period_start'])->format('Y-m')
            : now()->format('Y-m');

        $invoiceModel = Invoice::query()
            ->where('company_id', $subscription->company_id)
            ->where('subscription_id', $subscription->id)
            ->where('period', $period)
            ->whereNull('stripe_invoice_id')
            ->orderBy('id')
            ->first();

        if (! $invoiceModel) {
            Log::info('Stripe: Aucune facture interne à rapprocher', [
                'company_id' => $subscription->company_id,
                'stripe_invoice_id' => $stripeInvoiceId,
                'period' => $period,
            ]);

            return null;
        }

        $invoiceModel->forceFill(['stripe_invoice_id' => $stripeInvoiceId])->save();

        Log::info('Stripe: Facture interne rapprochée', [
            'company_id' => $invoiceModel->company_id,
            'invoice_id' => $invoiceModel->id,
            'stripe_invoice_id' => $stripeInvoiceId,
            'period' => $period,
        ]);

        return $invoiceModel;
    }

    private function handleInvoicePaid(array $invoice): void
    {
        // #7763 : lookup historique par stripe_invoice_id, avec repli sur le
        // rapprochement souscription + période si l'événement invoice.created
        // n'a pas été reçu (l'id Stripe est alors écrit ici).
        $invoiceModel = $this->reconcileStripeInvoice($invoice);

        if ($invoiceModel) {
            $amountPaid = (float) (($invoice['amount_paid'] ?? 0) / 100);
            if ($amountPaid <= 0) {
                $amountPaid = (float) ($invoiceModel->total ?? $invoiceModel->amount ?? 0);
            }

            $this->transitionInvoice($invoiceModel, InvoiceStatus::Paid, [
                'paid_at' => now(),
                'payment_method' => 'stripe',
            ]);

            $payment = Payment::query()->firstOrCreate(
                [
                    'provider_reference' => $invoice['charge'] ?? $invoice['payment_intent'] ?? $invoice['id'] ?? null,
                ],
                [
                    'invoice_id' => $invoiceModel->id,
                    'company_id' => $invoiceModel->company_id,
                    'amount' => $amountPaid,
                    'currency' => strtoupper((string) ($invoice['currency'] ?? $invoiceModel->currency ?? 'eur')),
                    'method' => 'card',
                    'status' => 'completed',
                    'paid_at' => now(),
                    'created_at' => now(),
                ]
            );

            // GROWTH MODULE: Dispatch SubscriptionPaid event
            event(new SubscriptionPaid($payment));

            if ($invoiceModel->subscription) {
                $this->transitionSubscription($invoiceModel->subscription, SubscriptionStatus::Active);
            }
        }

        $subscriptionId = $invoice['subscription'] ?? null;
        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if ($subscription) {
            $this->transitionSubscription($subscription, SubscriptionStatus::Active, [
                'current_period_start' => isset($invoice['period_start'])
                    ? Carbon::createFromTimestamp($invoice['period_start'])
                    : now(),
                'current_period_end' => isset($invoice['period_end'])
                    ? Carbon::createFromTimestamp($invoice['period_end'])
                    : now()->addMonth(),
            ]);
        }
    }

    private function handleInvoicePaymentFailed(array $invoice): void
    {
        $invoiceModel = Invoice::query()
            ->where('stripe_invoice_id', $invoice['id'] ?? '')
            ->first();

        if (! $invoiceModel) {
            return;
        }

        $this->transitionInvoice($invoiceModel, InvoiceStatus::Overdue);

        if ($invoiceModel->subscription) {
            $this->transitionSubscription($invoiceModel->subscription, SubscriptionStatus::PastDue);
        }
    }

    private function handleSubscriptionUpdated(array $subscription): void
    {
        $sub = Subscription::query()
            ->where('stripe_subscription_id', $subscription['id'] ?? '')
            ->first();

        if (! $sub) {
            return;
        }

        // Mapping Stripe → machine à états Leopardo (DEP-BC21 #6246) :
        // `unpaid` (paiement échoué, défaut continu) → `past_due` ;
        // `incomplete_expired` (checkout jamais complété) → `expired` ;
        // `trialing` → `trial`. Un statut inconnu conserve l'état local.
        $status = match ($subscription['status'] ?? '') {
            'active' => SubscriptionStatus::Active,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled', 'cancelled' => SubscriptionStatus::Cancelled,
            'unpaid' => SubscriptionStatus::PastDue,
            'incomplete_expired' => SubscriptionStatus::Expired,
            'trialing' => SubscriptionStatus::Trial,
            default => null,
        };

        if ($status === null) {
            Log::info('Stripe: Statut de souscription non mappé — état local conservé', [
                'company_id' => $sub->company_id,
                'stripe_status' => $subscription['status'] ?? 'unknown',
            ]);

            return;
        }

        $this->transitionSubscription($sub, $status);
    }

    private function handleSubscriptionDeleted(array $subscription): void
    {
        $sub = Subscription::query()
            ->where('stripe_subscription_id', $subscription['id'] ?? '')
            ->first();

        if ($sub) {
            $this->transitionSubscription($sub, SubscriptionStatus::Cancelled, [
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

    /**
     * Handle payment refund from Stripe.
     */
    private function handleChargeRefunded(array $charge): void
    {
        $payment = Payment::where('provider_reference', $charge['payment_intent'] ?? $charge['id'])
            ->first();

        if ($payment) {
            $payment->update(['status' => 'refunded']);

            // GROWTH MODULE: Cancel pending commissions
            try {
                $partnerService = app(PartnerService::class);
                $partnerService->handlePaymentRefunded($payment);
            } catch (Throwable $e) {
                Log::warning('PartnerService: Failed to handle refund', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Transition d'état GARDÉE pour les factures (DEP-BC21 #6248) — même
     * contrat que les souscriptions : jamais d'exception remontée au webhook,
     * un refus de transition (ex. facture déjà payée ou annulée) est
     * journalisé et l'état courant est conservé.
     *
     * @param  array<string, mixed>  $extra  attributs additionnels (paid_at, payment_method…)
     */
    private function transitionInvoice(Invoice $invoice, InvoiceStatus $target, array $extra = []): void
    {
        try {
            $invoice->transitionTo($target, $extra);
        } catch (InvalidArgumentException $e) {
            Log::warning('Stripe: Transition de facture refusée par la machine à états', [
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'from' => $invoice->status,
                'to' => $target->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Transition d'état GARDÉE pour les webhooks (DEP-BC21 #6246).
     *
     * Toutes les écritures de `status` de souscription passent par la machine
     * à états (`transitionTo`). Un webhook provider ne doit JAMAIS planter ni
     * faire échouer le traitement d'un autre événement parce qu'une transition
     * métier est refusée : on journalise un warning et on conserve l'état
     * courant — l'idempotence du registre #5444 reste intacte.
     *
     * Règle « cancelled est sticky » : une souscription résiliée LOCALEMENT
     * (`POST /billing/subscription/cancel`) ne doit pas être réactivée par un
     * écho webhook (ex. `invoice.paid` ou `subscription.updated=active` après
     * le cancel). Seule une réactivation EXPLICITE la fait repasser en active
     * — nouvel abonnement Stripe (`checkout.session.completed`, qui passe
     * `$allowReactivation = true`) ou endpoint `renew`/`upgrade`.
     *
     * @param  array<string, mixed>  $extra  attributs additionnels (period_end, cancelled_at…)
     */
    private function transitionSubscription(
        Subscription $subscription,
        SubscriptionStatus $target,
        array $extra = [],
        bool $allowReactivation = false,
    ): void {
        if (
            ! $allowReactivation
            && $subscription->status === SubscriptionStatus::Cancelled->value
            && $target === SubscriptionStatus::Active
        ) {
            Log::info('Stripe: Réactivation refusée — souscription résiliée localement', [
                'company_id' => $subscription->company_id,
                'event_target' => $target->value,
            ]);

            return;
        }

        try {
            $subscription->transitionTo($target, $extra);
        } catch (InvalidArgumentException $e) {
            Log::warning('Stripe: Transition de souscription refusée par la machine à états', [
                'company_id' => $subscription->company_id,
                'from' => $subscription->status,
                'to' => $target->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
