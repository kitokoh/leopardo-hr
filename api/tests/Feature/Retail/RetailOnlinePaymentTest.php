<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Models\RetailPaymentIntent;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7812) — Paiement en ligne marketplace (mobile money / PSP,
 * chantier BC-21) : intent de paiement public (jeton obligatoire, montant
 * serveur, idempotence), webhook signé HMAC-SHA256 fail-closed
 * (réconciliation paid/failed, rejeu idempotent), commande marquée payée
 * (paiement `online` capturé) et COD du handoff BC-26 (#7811) annulé.
 */
class RetailOnlinePaymentTest extends TestCase
{
    use RefreshTenantDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.retail_market.webhook_secret' => self::WEBHOOK_SECRET]);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $company->setFeature('retail', true);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principal = $principal;
    }

    /**
     * @return array{0: int, 1: string, 2: string} [orderId, reference, trackingToken]
     */
    private function placeOnlineOrder(): array
    {
        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => true,
            'shop_name' => 'Boutique Paiement',
            'city' => 'Dakar',
            'currency' => 'XOF',
        ])->assertStatus(200);

        $location = $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-01',
        ])->assertStatus(201)->json('data');

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => $location['id'],
            'product_id' => $product['id'],
            'quantity_delta' => 10,
            'reason_code' => 'purchase',
        ])->assertStatus(201);

        $data = $this->postJson('/api/v1/public/market/orders', [
            'seller' => (string) $this->company->slug,
            'items' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
            'customer' => [
                'name' => 'Awa Ndiaye',
                'phone' => '+221770000000',
                'email' => null,
            ],
            'delivery' => [
                'address' => '12 rue des Manguiers',
                'city' => 'Dakar',
                'notes' => null,
            ],
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201)->json('data');

        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->company->id)
            ->where('reference', $data['reference'])
            ->firstOrFail();

        return [(int) $order->id, (string) $order->reference, (string) $order->tracking_token];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postSignedWebhook(array $payload, ?string $secret = self::WEBHOOK_SECRET): \Illuminate\Testing\TestResponse
    {
        $raw = (string) json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $raw, (string) $secret);

        return $this->call(
            'POST',
            '/api/v1/public/market/payments/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Leopardo-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $raw,
        );
    }

    public function test_pay_creates_intent_with_server_amount_and_is_idempotent(): void
    {
        [, $reference, $token] = $this->placeOnlineOrder();

        $first = $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('pending', $first['status']);
        $this->assertSame(3_000, $first['amount_minor']);
        $this->assertSame('XOF', $first['currency']);
        $this->assertNotSame('', (string) $first['provider_reference']);

        // Rejeu : l'intent pending existant est retourné tel quel (200).
        $second = $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($first['provider_reference'], $second['provider_reference']);
        $this->assertSame(1, RetailPaymentIntent::query()
            ->where('company_id', (string) $this->company->id)
            ->count());
    }

    public function test_pay_requires_valid_tracking_token(): void
    {
        [, $reference] = $this->placeOnlineOrder();

        $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => str_repeat('0', 64)])
            ->assertStatus(404);
    }

    public function test_signed_webhook_marks_order_paid_and_cancels_cod(): void
    {
        [$orderId, $reference, $token] = $this->placeOnlineOrder();

        $intent = $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(201)
            ->json('data');

        $this->postSignedWebhook([
            'provider_reference' => $intent['provider_reference'],
            'status' => 'paid',
        ])->assertStatus(200)->assertJsonPath('data.status', 'paid');

        // Paiement online capturé côté commande.
        /** @var RetailOrderPayment $payment */
        $payment = RetailOrderPayment::query()
            ->where('company_id', (string) $this->company->id)
            ->where('order_id', $orderId)
            ->firstOrFail();
        $this->assertSame('online', $payment->method->value);
        $this->assertSame(3_000, (int) $payment->amount_minor);
        $this->assertSame('captured', $payment->status);

        // Rejeu du webhook : idempotent, aucun second paiement.
        $this->postSignedWebhook([
            'provider_reference' => $intent['provider_reference'],
            'status' => 'paid',
        ])->assertStatus(200);
        $this->assertSame(1, RetailOrderPayment::query()
            ->where('company_id', (string) $this->company->id)
            ->where('order_id', $orderId)
            ->count());

        // Suivi public : paiement affiché payé.
        $this->getJson("/api/v1/public/market/orders/{$reference}?token={$token}")
            ->assertStatus(200)
            ->assertJsonPath('data.payment.status', 'paid');

        // Handoff BC-26 (#7811) : commande payée en ligne → livraison SANS COD.
        Sanctum::actingAs($this->principal);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(200);

        /** @var Delivery $delivery */
        $delivery = Delivery::query()
            ->where('company_id', (string) $this->company->id)
            ->where('source', 'retail_online')
            ->where('source_reference', $reference)
            ->firstOrFail();
        $this->assertNull($delivery->cod_amount_minor);

        // Commande soldée : nouvelle demande de paiement refusée (422).
        $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(422);
    }

    public function test_webhook_is_fail_closed_on_signature(): void
    {
        [, $reference, $token] = $this->placeOnlineOrder();

        $intent = $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(201)
            ->json('data');

        // Mauvais secret → 400, aucune écriture.
        $this->postSignedWebhook([
            'provider_reference' => $intent['provider_reference'],
            'status' => 'paid',
        ], 'wrong-secret')->assertStatus(400);

        // Secret non configuré → 400 (fail-closed #2615).
        config(['services.retail_market.webhook_secret' => '']);
        $this->postSignedWebhook([
            'provider_reference' => $intent['provider_reference'],
            'status' => 'paid',
        ])->assertStatus(400);

        config(['services.retail_market.webhook_secret' => self::WEBHOOK_SECRET]);

        $this->assertSame('pending', RetailPaymentIntent::query()
            ->where('company_id', (string) $this->company->id)
            ->firstOrFail()
            ->status->value);
    }

    public function test_webhook_failed_marks_intent_failed_and_unknown_reference_404(): void
    {
        [, $reference, $token] = $this->placeOnlineOrder();

        $intent = $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(201)
            ->json('data');

        $this->postSignedWebhook([
            'provider_reference' => $intent['provider_reference'],
            'status' => 'failed',
            'failure_reason' => 'INSUFFICIENT_FUNDS',
        ])->assertStatus(200)->assertJsonPath('data.status', 'failed');

        $this->assertSame(0, RetailOrderPayment::query()
            ->where('company_id', (string) $this->company->id)
            ->count());

        // Référence inconnue → 404 fail-closed.
        $this->postSignedWebhook([
            'provider_reference' => 'RPI-2026-UNKNOWN',
            'status' => 'paid',
        ])->assertStatus(404);

        // Après un échec, une nouvelle demande recrée un intent pending.
        $retry = $this->postJson("/api/v1/public/market/orders/{$reference}/pay", ['token' => $token])
            ->assertStatus(201)
            ->json('data');
        $this->assertNotSame($intent['provider_reference'], $retry['provider_reference']);
    }
}
