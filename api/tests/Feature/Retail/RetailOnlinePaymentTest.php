<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOnlinePaymentIntent;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7812) — Paiement en ligne marketplace (MockProvider) :
 * checkout `payment_method = online` (intent cree, reponse enrichie, rejeu
 * idempotent), webhook signe fail-closed (signature valide → commande
 * payee ; invalide → 401 sans effet ; rejeu → 200 sans double effet ;
 * failed → commande non payee), reconciliation console, remboursement
 * vendeur (RBAC + isolation tenant) et suivi public `payment.{method,status}`.
 */
class RetailOnlinePaymentTest extends TestCase
{
    use RefreshTenantDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret-7812';

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'retail.payments.provider' => 'mock',
            'retail.payments.mock.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('retail', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyB->setFeature('retail', true);
        $companyB->save();
        $this->companyB = $companyB;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->employeeA = $this->employee($this->companyA, 'employee');
        $this->principalB = $this->employee($this->companyB, 'principal');
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = [
            'company_id' => $company->id,
            'status' => 'active',
        ];

        if ($managerRole === 'employee') {
            $attributes['role'] = 'employee';
        } else {
            $attributes['role'] = 'manager';
            $attributes['manager_role'] = $managerRole;
        }

        /** @var Employee $employee */
        $employee = Employee::factory()->create($attributes);

        return $employee;
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * Prepare une boutique en ligne complete (reglages actives, emplacement,
     * produit publie + visible) et retourne le produit.
     *
     * @return array<string, mixed>
     */
    private function sellerWithProduct(Employee $principal, string $currency, string $skuSuffix = '01'): array
    {
        $this->actingAsUser($principal);

        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => true,
            'shop_name' => 'Boutique '.$skuSuffix,
            'city' => 'Alger',
            'currency' => $currency,
        ])->assertStatus(200);

        $this->postJson('/api/v1/retail/locations', [
            'name' => 'Magasin principal',
            'code' => 'STORE-'.$skuSuffix,
        ])->assertStatus(201);

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit en ligne '.$skuSuffix,
            'sku' => 'SKU-PAY-'.$skuSuffix,
            'price_minor' => 2_500,
            'currency' => $currency,
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkoutBody(string $sellerSlug, int $productId, array $overrides = []): array
    {
        return array_merge([
            'seller' => $sellerSlug,
            'items' => [['product_id' => $productId, 'quantity' => 2]],
            'customer' => [
                'name' => 'Yacine Benali',
                'phone' => '+213550000000',
                'email' => 'yacine@example.test',
            ],
            'delivery' => [
                'address' => '5 rue Didouche Mourad',
                'city' => 'Alger',
                'notes' => null,
            ],
            'payment_method' => 'online',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    /**
     * Poste un webhook mock signe (HMAC-SHA256 du corps brut, header
     * `signature`).
     *
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postSignedWebhook(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $raw = (string) json_encode($payload);
        $signature = hash_hmac('sha256', $raw, $secret ?? self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/v1/public/market/payments/webhook/mock',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_SIGNATURE' => $signature,
            ],
            $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function succeededEvent(string $intentReference, int $amountMinor = 5_000): array
    {
        return [
            'type' => 'payment.succeeded',
            'data' => [
                'intent_reference' => $intentReference,
                'amount_minor' => $amountMinor,
                'currency' => 'DZD',
            ],
        ];
    }

    private function intentByReference(string $reference): RetailOnlinePaymentIntent
    {
        /** @var RetailOnlinePaymentIntent $intent */
        $intent = RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->where('intent_reference', $reference)
            ->firstOrFail();

        return $intent;
    }

    private function orderByReference(string $companyId, string $reference): RetailOrder
    {
        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('reference', $reference)
            ->firstOrFail();

        return $order;
    }

    public function test_online_checkout_creates_payment_intent_and_is_idempotent(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $body = $this->checkoutBody((string) $this->companyA->slug, (int) $product['id']);

        $first = $this->postJson('/api/v1/public/market/orders', $body)
            ->assertStatus(201)
            ->assertJsonPath('data.payment.method', 'online')
            ->assertJsonPath('data.payment.status', 'pending')
            ->json('data');

        $reference = (string) $first['payment']['intent_reference'];

        $this->assertSame(64, strlen($reference));
        $this->assertIsString($first['payment']['checkout_url']);
        $this->assertStringContainsString($reference, (string) $first['payment']['checkout_url']);

        $intent = $this->intentByReference($reference);
        $order = $this->orderByReference((string) $this->companyA->id, (string) $first['reference']);

        // Intent lie a la commande, montant serveur en minor units.
        $this->assertSame((int) $order->id, $intent->order_id);
        $this->assertSame(5_000, $intent->amount_minor);
        $this->assertSame('DZD', $intent->currency);
        $this->assertSame('mock', $intent->provider);
        $this->assertSame('pending', $intent->status->value);
        $this->assertSame('online', $order->payment_method?->value);
        $this->assertSame('pending', $order->payment_status);

        // Rejeu meme cle → 200, MEME payload (meme intent, pas de doublon).
        $replay = $this->postJson('/api/v1/public/market/orders', $body)
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($first, $replay);
        $this->assertSame(1, RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $this->companyA->id)
            ->count());
    }

    public function test_cash_checkout_keeps_cod_default_without_intent(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
            ['payment_method' => 'cash'],
        ))->assertStatus(201)->json('data');

        $this->assertSame('cash', $data['payment']['method']);
        $this->assertNull($data['payment']['intent_reference']);
        $this->assertNull($data['payment']['checkout_url']);

        $this->assertSame(0, RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->count());
    }

    public function test_valid_signed_webhook_marks_intent_and_order_paid(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];

        $this->postSignedWebhook($this->succeededEvent($reference))
            ->assertStatus(200)
            ->assertJsonPath('data.applied', true);

        $intent = $this->intentByReference($reference);
        $order = $this->orderByReference((string) $this->companyA->id, (string) $data['reference']);

        $this->assertSame('succeeded', $intent->status->value);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);

        // Trace d'encaissement RetailOrderPayment (online/captured).
        /** @var RetailOrderPayment $payment */
        $payment = RetailOrderPayment::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $this->companyA->id)
            ->where('order_id', (int) $order->id)
            ->firstOrFail();

        $this->assertSame('online', $payment->method->value);
        $this->assertSame('captured', $payment->status);
        $this->assertSame(5_000, $payment->amount_minor);

        // Suivi public : payment.{method,status} exposes, rien d'autre.
        $tracked = $this->getJson('/api/v1/public/market/orders/'.$data['reference'].'?token='.$data['tracking_token'])
            ->assertStatus(200)
            ->assertJsonPath('data.payment.method', 'online')
            ->assertJsonPath('data.payment.status', 'paid');

        $this->assertSame(['method', 'status'], array_keys($tracked->json('data.payment')));
    }

    public function test_webhook_with_invalid_signature_is_rejected_without_effect(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];

        // Mauvais secret → 401, aucun effet.
        $this->postSignedWebhook($this->succeededEvent($reference), 'wrong-secret')
            ->assertStatus(401);

        // Signature absente → 401 aussi.
        $this->postJson('/api/v1/public/market/payments/webhook/mock', $this->succeededEvent($reference))
            ->assertStatus(401);

        $this->assertSame('pending', $this->intentByReference($reference)->status->value);
        $this->assertSame('pending', $this->orderByReference(
            (string) $this->companyA->id,
            (string) $data['reference'],
        )->payment_status);

        // Provider inconnu → 404 fail-closed.
        $this->postJson('/api/v1/public/market/payments/webhook/unknown', [])
            ->assertStatus(404);
    }

    public function test_webhook_replay_is_idempotent_without_double_effect(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];

        $this->postSignedWebhook($this->succeededEvent($reference))
            ->assertStatus(200)
            ->assertJsonPath('data.applied', true);

        // Rejeu strict → 200 SANS double effet.
        $this->postSignedWebhook($this->succeededEvent($reference))
            ->assertStatus(200)
            ->assertJsonPath('data.applied', false);

        $order = $this->orderByReference((string) $this->companyA->id, (string) $data['reference']);

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, RetailOrderPayment::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $this->companyA->id)
            ->where('order_id', (int) $order->id)
            ->count());
    }

    public function test_failed_webhook_leaves_order_unpaid(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];

        $this->postSignedWebhook([
            'type' => 'payment.failed',
            'data' => ['intent_reference' => $reference],
        ])->assertStatus(200)->assertJsonPath('data.applied', true);

        $intent = $this->intentByReference($reference);
        $order = $this->orderByReference((string) $this->companyA->id, (string) $data['reference']);

        $this->assertSame('failed', $intent->status->value);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame('pending', $order->fulfillment_status?->value);

        // Un webhook `succeeded` sur un intent deja `failed` (terminal) est
        // ignore sans effet.
        $this->postSignedWebhook($this->succeededEvent($reference))
            ->assertStatus(200)
            ->assertJsonPath('data.applied', false);
    }

    public function test_webhook_with_amount_mismatch_is_ignored(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];

        // Montant notifie incoherent → ignore (anti-fraude), intent inchange.
        $this->postSignedWebhook($this->succeededEvent($reference, 1))
            ->assertStatus(200)
            ->assertJsonPath('data.applied', false);

        $this->assertSame('pending', $this->intentByReference($reference)->status->value);
    }

    public function test_reconcile_command_applies_provider_status(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];
        $intent = $this->intentByReference($reference);

        // Simule la reponse du PSP (MockProvider::verifyIntent) + vieillit
        // l'intent au-dela du seuil de reconciliation.
        $payload = is_array($intent->provider_payload) ? $intent->provider_payload : [];
        $intent->forceFill([
            'provider_payload' => $payload + ['mock_verify_status' => 'succeeded'],
            'created_at' => now()->subHour(),
        ])->save();

        $command = $this->artisan('retail:payments:reconcile', ['--minutes' => 30]);
        \assert($command instanceof \Illuminate\Testing\PendingCommand);
        // PendingCommand est LAZY (execution au destructeur) : run() force
        // l'execution AVANT les assertions qui suivent.
        $this->assertSame(0, $command->run());

        $this->assertSame('succeeded', $this->intentByReference($reference)->status->value);
        $this->assertSame('paid', $this->orderByReference(
            (string) $this->companyA->id,
            (string) $data['reference'],
        )->payment_status);
    }

    public function test_seller_refund_requires_succeeded_intent_and_manager_role(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];
        $order = $this->orderByReference((string) $this->companyA->id, (string) $data['reference']);

        // Intent encore pending → 422 PAYMENT_NOT_REFUNDABLE.
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/online/orders/{$order->id}/refund")
            ->assertStatus(422);

        $this->postSignedWebhook($this->succeededEvent($reference))->assertStatus(200);

        // Employe simple → 403 (RetailOrderPolicy@pay deny-by-default).
        $this->actingAsUser($this->employeeA);
        $this->postJson("/api/v1/retail/online/orders/{$order->id}/refund")
            ->assertStatus(403);

        // Principal → remboursement OK, trace auditable.
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/online/orders/{$order->id}/refund")
            ->assertStatus(200)
            ->assertJsonPath('data.payment.status', 'refunded')
            ->assertJsonPath('data.payment_status', 'refunded');

        $intent = $this->intentByReference($reference);
        $this->assertSame('refunded', $intent->status->value);
        $this->assertIsArray($intent->provider_payload);
        $this->assertArrayHasKey('refund', $intent->provider_payload);

        /** @var RetailOrderPayment $payment */
        $payment = RetailOrderPayment::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $this->companyA->id)
            ->where('order_id', (int) $order->id)
            ->firstOrFail();

        $this->assertSame('refunded', $payment->status);

        // Second remboursement → 422 (intent deja refunded, terminal).
        $this->postJson("/api/v1/retail/online/orders/{$order->id}/refund")
            ->assertStatus(422);
    }

    public function test_refund_is_tenant_isolated(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');
        $this->sellerWithProduct($this->principalB, 'XOF', 'B2');

        $data = $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
        ))->assertStatus(201)->json('data');

        $reference = (string) $data['payment']['intent_reference'];
        $order = $this->orderByReference((string) $this->companyA->id, (string) $data['reference']);

        $this->postSignedWebhook($this->succeededEvent($reference))->assertStatus(200);

        // Principal du tenant B sur la commande du tenant A → 404 fail-closed.
        $this->actingAsUser($this->principalB);
        $this->postJson("/api/v1/retail/online/orders/{$order->id}/refund")
            ->assertStatus(404);

        $this->assertSame('succeeded', $this->intentByReference($reference)->status->value);
    }

    public function test_checkout_rejects_unknown_payment_method(): void
    {
        $product = $this->sellerWithProduct($this->principalA, 'DZD');

        $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            (int) $product['id'],
            ['payment_method' => 'bitcoin'],
        ))->assertStatus(422);
    }
}
