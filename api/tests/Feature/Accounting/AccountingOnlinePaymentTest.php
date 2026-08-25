<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5272 — Paiement en ligne des documents (ADR-0017, option A).
 *
 * US1 — checkout : routage Chargily (DZ) / Stripe (FR/UK/US/CI), documents
 *       non payables refusés (422), pays sans passerelle fail-closed (422),
 *       RBAC comptable/principal.
 * US2 — webhook : signature HMAC fail-closed (401), rapprochement automatique
 *       idempotent (rejeu sans doublon), anti-fraude montant > solde (422),
 *       paiement partiel → partially_paid.
 * US3 — annulation/expiration : document inchangé, aucun paiement fantôme.
 */
class AccountingOnlinePaymentTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ', string $currency = 'DZD'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function forgetCompany(): void
    {
        app()->forgetInstance('current_company');
    }

    private function contact(Company $company): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => 'client@exemple.dz',
        ]);

        return $contact;
    }

    private function document(
        Company $company,
        string $status = 'sent',
        float $ttc = 1190.0,
        string $currency = 'DZD',
        string $number = 'FAC-2026-0001',
    ): AccountingDocument {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => $number,
            'status' => $status,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-05',
            'due_date' => '2026-08-20',
            'currency' => $currency,
            'subtotal_ht' => round($ttc / 1.19, 2),
            'tax_amount' => round($ttc - $ttc / 1.19, 2),
            'total_ttc' => $ttc,
            'tva_rate' => 19.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    private function manager(Company $company, string $managerRole): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    // ── US1 : checkout ───────────────────────────────────────────────────────

    public function test_checkout_routes_dz_company_to_chargily(): void
    {
        Config::set('services.chargily.api_key', 'chargily_test_key');
        Config::set('services.chargily.mode', 'test');

        Http::fake([
            'https://pay.chargily.net/test/api/v2/checkouts' => Http::response([
                'id' => 'ch_chargily_001',
                'checkout_url' => 'https://pay.chargily.net/test/checkout/ch_chargily_001',
                'amount' => 119000,
                'currency' => 'dzd',
            ], 201),
        ]);

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'comptable'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/checkout');

        $response->assertOk();
        $response->assertJsonPath('data.gateway', 'chargily');
        $response->assertJsonPath('data.checkout_url', 'https://pay.chargily.net/test/checkout/ch_chargily_001');
        $this->assertNotNull($response->json('data.expires_at'));

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://pay.chargily.net/test/api/v2/checkouts'
                && $payload['amount'] === 119000
                && $payload['currency'] === 'dzd'
                && $payload['metadata']['company_id'] !== null;
        });
    }

    public function test_checkout_routes_fr_company_to_stripe(): void
    {
        Config::set('services.stripe.secret', 'stripe_test_key');

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_stripe_001',
                'url' => 'https://checkout.stripe.com/c/pay/cs_stripe_001',
                'expires_at' => time() + 3600,
            ], 200),
        ]);

        $company = $this->company('FR', 'EUR');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'EUR');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'principal'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/checkout');

        $response->assertOk();
        $response->assertJsonPath('data.gateway', 'stripe');
        $response->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_stripe_001');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request['mode'] === 'payment'
                && $request['line_items[0][price_data][unit_amount]'] === 119000
                && $request['line_items[0][price_data][currency]'] === 'eur';
        });
    }

    public function test_checkout_draft_document_is_rejected(): void
    {
        Config::set('services.chargily.api_key', 'chargily_test_key');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $draft = $this->document($company, 'draft', 1190.0, 'DZD');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'comptable'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$draft->id.'/checkout');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'DOCUMENT_NOT_SENDABLE');
    }

    public function test_checkout_paid_document_is_rejected(): void
    {
        Config::set('services.chargily.api_key', 'chargily_test_key');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $paid = $this->document($company, 'paid', 1190.0, 'DZD');
        $paid->update(['paid_amount' => 1190.0]);
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'comptable'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$paid->id.'/checkout');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'DOCUMENT_NOT_SENDABLE');
    }

    public function test_checkout_unsupported_country_fails_closed(): void
    {
        // Pays hors périmètre ADR-0017 (SN → pas de passerelle) : fail-closed.
        Config::set('services.chargily.api_key', 'chargily_test_key');
        Config::set('services.stripe.secret', 'stripe_test_key');

        $company = $this->company('SN', 'XOF');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'XOF');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'principal'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/checkout');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'PAYMENT_GATEWAY_NOT_CONFIGURED');
    }

    public function test_checkout_requires_comptable_or_principal(): void
    {
        Config::set('services.chargily.api_key', 'chargily_test_key');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'rh'));

        $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/checkout')->assertForbidden();
    }

    // ── US2 : webhook Chargily ───────────────────────────────────────────────

    private function chargilySignature(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, 'chargily_test_secret');
    }

    private function stripeSignature(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'stripe_test_secret');

        return 't='.$timestamp.',v1='.$signature;
    }

    /**
     * Envoie un webhook avec le payload JSON brut comme corps de requête —
     * la signature HMAC est calculée sur EXACTEMENT ces octets (le TestCase
     * Laravel encode $data avec json_encode + JSON_THROW_ON_ERROR, identique).
     *
     * @param  array<string, mixed>  $data
     */
    private function postChargilyWebhook(string $url, array $data): TestResponse
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        return $this->postJson($url, $data, ['X-Chargily-Signature' => $this->chargilySignature($payload)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postStripeWebhook(string $url, array $data): TestResponse
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        return $this->postJson($url, $data, ['Stripe-Signature' => $this->stripeSignature($payload)]);
    }

    public function test_webhook_chargily_paid_reconciles_payment_automatically(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        $response = $this->postChargilyWebhook('/api/v1/accounting/payments/webhook/chargily', [
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'ch_chargily_001',
                'amount' => 119000,
                'currency' => 'dzd',
                'metadata' => [
                    'document_id' => $invoice->id,
                    'company_id' => $company->id,
                    'document_number' => 'FAC-2026-0001',
                ],
                'payment_method' => 'cib',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('received', true);

        // Paiement créé ET rapproché (matched) — « sans intervention », DoD.
        $payment = AccountingPayment::query()->where('gateway_payment_id', 'ch_chargily_001')->first();
        $this->assertNotNull($payment);
        $this->assertSame('online_chargily', $payment->method);
        $this->assertSame('matched', $payment->status);
        $this->assertSame('ch_chargily_001', $payment->reference);
        $this->assertSame(1190.0, $payment->amount);
        $this->assertNotNull($payment->reconciled_at);

        $invoice->refresh();
        $this->assertSame(1190.0, $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_webhook_replay_creates_no_duplicate_payment(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        $url = '/api/v1/accounting/payments/webhook/chargily';
        $data = [
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'ch_chargily_001',
                'amount' => 119000,
                'currency' => 'dzd',
                'metadata' => ['document_id' => $invoice->id, 'company_id' => $company->id],
            ],
        ];

        $this->postChargilyWebhook($url, $data)->assertOk();
        $this->postChargilyWebhook($url, $data)->assertOk();

        $this->assertSame(1, AccountingPayment::query()->count());
        $this->assertSame(1190.0, $invoice->refresh()->paid_amount);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_webhook_invalid_signature_is_rejected_fail_closed(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        $data = [
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'ch_chargily_001',
                'amount' => 119000,
                'currency' => 'dzd',
                'metadata' => ['document_id' => $invoice->id, 'company_id' => $company->id],
            ],
        ];

        $response = $this->postJson(
            '/api/v1/accounting/payments/webhook/chargily',
            $data,
            ['X-Chargily-Signature' => 'sha256=invalide']
        );

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'WEBHOOK_SIGNATURE_INVALID');
        $this->assertSame(0, AccountingPayment::query()->count());
    }

    public function test_webhook_amount_mismatch_is_rejected(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        // Montant notifié : 1 200 DZD pour un solde de 1 190 → dépasse le solde
        // (anti-fraude US2.4).
        $response = $this->postChargilyWebhook('/api/v1/accounting/payments/webhook/chargily', [
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'ch_chargily_001',
                'amount' => 120000,
                'currency' => 'dzd',
                'metadata' => ['document_id' => $invoice->id, 'company_id' => $company->id],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'PAYMENT_AMOUNT_MISMATCH');
        $this->assertSame(0, AccountingPayment::query()->count());
        $this->assertSame('sent', $invoice->refresh()->status);
    }

    public function test_webhook_partial_payment_marks_document_partially_paid(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        $url = '/api/v1/accounting/payments/webhook/chargily';
        $partial = fn (string $id, int $amount): array => [
            'type' => 'checkout.paid',
            'data' => [
                'id' => $id,
                'amount' => $amount,
                'currency' => 'dzd',
                'metadata' => ['document_id' => $invoice->id, 'company_id' => $company->id],
            ],
        ];

        // Premier règlement partiel : 500 DZD (≤ solde → légitime, US2.5).
        $this->postChargilyWebhook($url, $partial('ch_partial_1', 50000))->assertOk();

        $invoice->refresh();
        $this->assertSame(500.0, $invoice->paid_amount);
        $this->assertSame('partially_paid', $invoice->status);

        // Solde restant : 690 DZD.
        $this->postChargilyWebhook($url, $partial('ch_partial_2', 69000))->assertOk();

        $invoice->refresh();
        $this->assertSame(1190.0, $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(2, AccountingPayment::query()->count());
    }

    public function test_webhook_unknown_gateway_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/v1/accounting/payments/webhook/paypal',
            ['type' => 'checkout.paid'],
            ['X-Chargily-Signature' => 'sha256=abc']
        );

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'WEBHOOK_SIGNATURE_INVALID');
    }

    // ── US2 : webhook Stripe ─────────────────────────────────────────────────

    public function test_webhook_stripe_paid_reconciles_payment_automatically(): void
    {
        Config::set('services.stripe.webhook_secret', 'stripe_test_secret');

        $company = $this->company('FR', 'EUR');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'EUR');
        $this->forgetCompany();

        $response = $this->postStripeWebhook('/api/v1/accounting/payments/webhook/stripe', [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_stripe_001',
                    'amount_total' => 119000,
                    'currency' => 'eur',
                    'payment_status' => 'paid',
                    'metadata' => ['document_id' => $invoice->id, 'company_id' => $company->id],
                ],
            ],
        ]);

        $response->assertOk();

        $payment = AccountingPayment::query()->where('gateway_payment_id', 'cs_stripe_001')->first();
        $this->assertNotNull($payment);
        $this->assertSame('online_stripe', $payment->method);
        $this->assertSame('matched', $payment->status);
        $this->assertSame(1190.0, $payment->amount);
        $this->assertSame('paid', $invoice->refresh()->status);
    }

    // ── US3 : annulation / expiration ────────────────────────────────────────

    public function test_webhook_cancelled_checkout_leaves_document_unchanged(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $company = $this->company('DZ', 'DZD');
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        $response = $this->postChargilyWebhook('/api/v1/accounting/payments/webhook/chargily', [
            'type' => 'checkout.canceled',
            'data' => [
                'id' => 'ch_chargily_001',
                'amount' => 119000,
                'currency' => 'dzd',
                'metadata' => ['document_id' => $invoice->id, 'company_id' => $company->id],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(0, AccountingPayment::query()->count());
        $this->assertSame('sent', $invoice->refresh()->status);
        $this->assertSame(0.0, $invoice->paid_amount);
    }

    // ── Isolation tenant ─────────────────────────────────────────────────────

    public function test_webhook_does_not_reconcile_foreign_document(): void
    {
        Config::set('services.chargily.webhook_secret', 'chargily_test_secret');

        $companyA = $this->company('DZ', 'DZD');
        $this->bindCompany($companyA);
        $invoiceA = $this->document($companyA, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        $companyB = $this->company('DZ', 'DZD');
        $this->bindCompany($companyB);
        $invoiceB = $this->document($companyB, 'sent', 1190.0, 'DZD');
        $this->forgetCompany();

        // Webhook signé avec les metadata du tenant A mais l'id de document du
        // tenant B : le scope BelongsToCompany doit empêcher le rapprochement.
        $response = $this->postChargilyWebhook('/api/v1/accounting/payments/webhook/chargily', [
            'type' => 'checkout.paid',
            'data' => [
                'id' => 'ch_cross_tenant',
                'amount' => 119000,
                'currency' => 'dzd',
                'metadata' => ['document_id' => $invoiceB->id, 'company_id' => $companyA->id],
            ],
        ]);

        // Document étranger introuvable dans le tenant A → ignoré, aucun
        // paiement fantôme (l'identifiant unique gateway n'est pas consommé).
        $response->assertOk();
        $this->assertSame(0, AccountingPayment::query()->count());
        $this->assertSame('sent', $invoiceB->refresh()->status);
        $this->assertSame('sent', $invoiceA->refresh()->status);
    }
}
