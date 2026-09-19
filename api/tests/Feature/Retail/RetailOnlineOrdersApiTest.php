<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7808) — Commandes en ligne du marketplace : checkout
 * invité (prix serveur, idempotence, référence WEB-…, jeton 64 hex),
 * suivi public fail-closed (référence + jeton), gestion vendeur
 * (réglages, transitions confirm/ready/ship/deliver/cancel, stock via
 * RetailStockService, 422 INVALID_TRANSITION), RBAC et isolation tenant.
 */
class RetailOnlineOrdersApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    private RetailLocation $locationA;

    private RetailProduct $productA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyA->setFeature('retail', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('retail', true);
        $companyB->save();
        $this->companyB = $companyB;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->employeeA = $this->employee($this->companyA, 'employee');
        $this->principalB = $this->employee($this->companyB, 'principal');

        /** @var RetailLocation $locationA */
        $locationA = RetailLocation::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-01',
        ]);
        $this->locationA = $locationA;

        RetailOnlineSettings::query()->create([
            'company_id' => (string) $this->companyA->id,
            'slug' => 'boutique-dakar',
            'display_name' => 'Boutique Dakar',
            'enabled' => true,
            'location_id' => $this->locationA->id,
        ]);

        /** @var RetailProduct $productA */
        $productA = RetailProduct::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Jus de bissap 50cl',
            'slug' => 'jus-de-bissap-50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
            'status' => 'published',
            'online_visible' => true,
        ]);
        $this->productA = $productA;

        RetailStockLevel::query()->create([
            'company_id' => (string) $this->companyA->id,
            'location_id' => $this->locationA->id,
            'product_id' => $this->productA->id,
            'quantity' => '10.000',
        ]);
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = ['company_id' => $company->id, 'status' => 'active'];

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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkout(array $overrides = []): array
    {
        return $this->postJson('/api/v1/public/market/orders', array_replace_recursive([
            'seller' => 'boutique-dakar',
            'idempotency_key' => 'idem-'.uniqid(),
            'customer' => ['name' => 'Awa Ndiaye', 'phone' => '+221770000000', 'email' => 'awa@example.com'],
            'delivery' => ['address' => '12 rue du Port', 'city' => 'Dakar'],
            'lines' => [['product_id' => $this->productA->id, 'quantity' => 2]],
        ], $overrides))->assertStatus(201)->json('data');
    }

    public function test_guest_checkout_creates_pending_online_order_with_server_totals(): void
    {
        $data = $this->checkout();

        $this->assertMatchesRegularExpression('/^WEB-\d{8}-[A-Z0-9]{6}$/', $data['reference']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $data['tracking_token']);
        $this->assertSame('pending', $data['fulfillment_status']);
        $this->assertSame(3_000, $data['total_minor']);
        $this->assertSame('XOF', $data['currency']);

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->where('reference', $data['reference'])->firstOrFail();
        $this->assertSame('online', $order->source->value);
        $this->assertSame((string) $this->companyA->id, (string) $order->company_id);
        $this->assertSame('Awa Ndiaye', $order->customer_name);

        // Le stock n'est PAS touché avant la confirmation vendeur.
        $this->assertSame('10.000', RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)->firstOrFail()->quantity);
    }

    public function test_checkout_ignores_client_prices_and_is_idempotent(): void
    {
        $first = $this->postJson('/api/v1/public/market/orders', [
            'seller' => 'boutique-dakar',
            'idempotency_key' => 'idem-fixed',
            'customer' => ['name' => 'Awa', 'phone' => '+221770000000'],
            'lines' => [[
                'product_id' => $this->productA->id,
                'quantity' => 1,
                // Tentative de manipulation : ignorée, prix relu en base.
                'unit_price_minor' => 1,
            ]],
        ])->assertStatus(201)->json('data');

        $this->assertSame(1_500, $first['total_minor']);

        $replay = $this->postJson('/api/v1/public/market/orders', [
            'seller' => 'boutique-dakar',
            'idempotency_key' => 'idem-fixed',
            'customer' => ['name' => 'Awa', 'phone' => '+221770000000'],
            'lines' => [['product_id' => $this->productA->id, 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        $this->assertSame($first['reference'], $replay['reference']);
        $this->assertSame(1, RetailOrder::query()->withoutGlobalScope('company')
            ->where('idempotency_key', 'idem-fixed')->count());
    }

    public function test_checkout_is_fail_closed_on_seller_and_products(): void
    {
        // Boutique inconnue ou désactivée : 404.
        $payload = [
            'seller' => 'inconnue',
            'idempotency_key' => 'idem-x',
            'customer' => ['name' => 'Awa', 'phone' => '+221770000000'],
            'lines' => [['product_id' => $this->productA->id, 'quantity' => 1]],
        ];
        $this->postJson('/api/v1/public/market/orders', $payload)->assertNotFound();

        // Produit d'un AUTRE tenant chez ce vendeur : 422 (1 commande = 1 vendeur).
        /** @var RetailProduct $foreign */
        $foreign = RetailProduct::query()->create([
            'company_id' => (string) $this->companyB->id,
            'name' => 'Tajine',
            'slug' => 'tajine',
            'sku' => 'SKU-TAJINE',
            'price_minor' => 9_000,
            'currency' => 'MAD',
            'status' => 'published',
            'online_visible' => true,
        ]);

        $payload['seller'] = 'boutique-dakar';
        $payload['lines'] = [['product_id' => $foreign->id, 'quantity' => 1]];
        $this->postJson('/api/v1/public/market/orders', $payload)->assertStatus(422);

        // Produit non visible en ligne : 422.
        $this->productA->update(['online_visible' => false]);
        $payload['lines'] = [['product_id' => $this->productA->id, 'quantity' => 1]];
        $this->postJson('/api/v1/public/market/orders', $payload)->assertStatus(422);
    }

    public function test_public_tracking_requires_reference_and_token_fail_closed(): void
    {
        $data = $this->checkout();

        $tracked = $this->getJson('/api/v1/public/market/orders/'.$data['reference'].'?token='.$data['tracking_token'])
            ->assertOk()->json('data');

        $this->assertSame('pending', $tracked['fulfillment_status']);
        $this->assertSame('Jus de bissap 50cl', $tracked['items'][0]['product_name']);
        $payload = json_encode($tracked, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('company_id', $payload);
        $this->assertStringNotContainsString((string) $this->companyA->id, $payload);

        // Jeton absent, invalide ou d'une autre commande : 404 indistinct.
        $this->getJson('/api/v1/public/market/orders/'.$data['reference'])->assertNotFound();
        $this->getJson('/api/v1/public/market/orders/'.$data['reference'].'?token=abc')->assertNotFound();
        $this->getJson('/api/v1/public/market/orders/'.$data['reference'].'?token='.str_repeat('0', 64))->assertNotFound();
        $this->getJson('/api/v1/public/market/orders/WEB-INCONNUE?token='.$data['tracking_token'])->assertNotFound();
    }

    public function test_full_lifecycle_confirm_decrements_stock_then_deliver_completes(): void
    {
        $data = $this->checkout(); // quantity 2

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->withoutGlobalScope('company')
            ->where('reference', $data['reference'])->firstOrFail();

        Sanctum::actingAs($this->principalA);

        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/confirm')->assertOk()
            ->assertJsonPath('data.fulfillment_status', 'confirmed');

        $this->assertSame('8.000', RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)->firstOrFail()->quantity);

        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/ready')->assertOk()
            ->assertJsonPath('data.fulfillment_status', 'ready');
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/ship')->assertOk()
            ->assertJsonPath('data.fulfillment_status', 'shipped');
        $delivered = $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/deliver')->assertOk()->json('data');

        $this->assertSame('delivered', $delivered['fulfillment_status']);
        $this->assertSame('completed', $delivered['status']);
        $this->assertNotNull($delivered['confirmed_at']);
        $this->assertNotNull($delivered['delivered_at']);

        // Le suivi public reflète la progression.
        $this->assertSame('delivered', $this->getJson(
            '/api/v1/public/market/orders/'.$data['reference'].'?token='.$data['tracking_token']
        )->json('data.fulfillment_status'));
    }

    public function test_invalid_transitions_return_422_invalid_transition(): void
    {
        $data = $this->checkout();

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->withoutGlobalScope('company')
            ->where('reference', $data['reference'])->firstOrFail();

        Sanctum::actingAs($this->principalA);

        // pending -> ship / deliver / ready : refusés.
        foreach (['ship', 'deliver', 'ready'] as $action) {
            $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/'.$action)
                ->assertStatus(422)
                ->assertJsonPath('code', 'INVALID_TRANSITION');
        }

        // delivered est terminal.
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/confirm')->assertOk();
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/ready')->assertOk();
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/ship')->assertOk();
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/deliver')->assertOk();
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TRANSITION');
    }

    public function test_confirm_refuses_when_stock_is_insufficient(): void
    {
        RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)
            ->update(['quantity' => '1.000']);

        $data = $this->checkout(); // quantity 2

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->withoutGlobalScope('company')
            ->where('reference', $data['reference'])->firstOrFail();

        Sanctum::actingAs($this->principalA);

        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/confirm')->assertStatus(422);

        // Rien n'est persisté : commande toujours pending, stock intact.
        $this->assertSame('pending', $order->refresh()->fulfillment_status?->value);
        $this->assertSame('1.000', RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)->firstOrFail()->quantity);
    }

    public function test_cancel_after_confirm_restores_stock(): void
    {
        $data = $this->checkout(); // quantity 2

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->withoutGlobalScope('company')
            ->where('reference', $data['reference'])->firstOrFail();

        Sanctum::actingAs($this->principalA);

        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/confirm')->assertOk();
        $this->assertSame('8.000', RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)->firstOrFail()->quantity);

        $cancelled = $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/cancel')->assertOk()->json('data');

        $this->assertSame('cancelled', $cancelled['fulfillment_status']);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame('10.000', RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)->firstOrFail()->quantity);
    }

    public function test_cancel_before_confirm_does_not_touch_stock(): void
    {
        $data = $this->checkout();

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->withoutGlobalScope('company')
            ->where('reference', $data['reference'])->firstOrFail();

        Sanctum::actingAs($this->principalA);

        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/cancel')->assertOk()
            ->assertJsonPath('data.fulfillment_status', 'cancelled');

        $this->assertSame('10.000', RetailStockLevel::query()->withoutGlobalScope('company')
            ->where('product_id', $this->productA->id)->firstOrFail()->quantity);
    }

    public function test_seller_orders_are_tenant_isolated_and_rbac_guarded(): void
    {
        $data = $this->checkout();

        /** @var RetailOrder $order */
        $order = RetailOrder::query()->withoutGlobalScope('company')
            ->where('reference', $data['reference'])->firstOrFail();

        // Un autre tenant ne voit ni ne manipule la commande (404).
        Sanctum::actingAs($this->principalB);
        $this->getJson('/api/v1/retail/online/orders/'.$order->id)->assertNotFound();
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/confirm')->assertNotFound();
        $this->assertSame(0, count((array) $this->getJson('/api/v1/retail/online/orders')->json('data')));

        // Un employé du tenant lit mais ne transitionne pas (403).
        Sanctum::actingAs($this->employeeA);
        $this->getJson('/api/v1/retail/online/orders/'.$order->id)->assertOk();
        $this->postJson('/api/v1/retail/online/orders/'.$order->id.'/confirm')->assertForbidden();

        // La liste vendeur filtre par fulfillment_status.
        Sanctum::actingAs($this->principalA);
        $this->assertSame(1, $this->getJson('/api/v1/retail/online/orders?fulfillment_status=pending')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/retail/online/orders?fulfillment_status=delivered')->json('meta.total'));
    }

    public function test_settings_endpoints_and_publish_online_toggle(): void
    {
        // Lecture : membre du tenant.
        Sanctum::actingAs($this->employeeA);
        $this->getJson('/api/v1/retail/online/settings')->assertOk()
            ->assertJsonPath('data.slug', 'boutique-dakar');

        // Écriture : réservée principal/rh.
        $this->putJson('/api/v1/retail/online/settings', [
            'slug' => 'boutique-dakar',
            'display_name' => 'Boutique Dakar',
            'enabled' => true,
        ])->assertForbidden();

        Sanctum::actingAs($this->principalA);
        $this->putJson('/api/v1/retail/online/settings', [
            'slug' => 'boutique-dakar-2',
            'display_name' => 'Boutique Dakar',
            'enabled' => false,
            'location_id' => $this->locationA->id,
        ])->assertOk()->assertJsonPath('data.slug', 'boutique-dakar-2')
            ->assertJsonPath('data.enabled', false);

        // Slug déjà pris par un AUTRE tenant : 422.
        RetailOnlineSettings::query()->create([
            'company_id' => (string) $this->companyB->id,
            'slug' => 'souk-casa',
            'display_name' => 'Souk Casa',
            'enabled' => true,
        ]);
        $this->putJson('/api/v1/retail/online/settings', [
            'slug' => 'souk-casa',
            'display_name' => 'Boutique Dakar',
            'enabled' => true,
        ])->assertStatus(422);

        // publish-online / unpublish-online par produit.
        $this->postJson('/api/v1/retail/products/'.$this->productA->id.'/unpublish-online')->assertOk()
            ->assertJsonPath('data.online_visible', false);
        $this->postJson('/api/v1/retail/products/'.$this->productA->id.'/publish-online')->assertOk()
            ->assertJsonPath('data.online_visible', true);

        // Employé simple : refusé.
        Sanctum::actingAs($this->employeeA);
        $this->postJson('/api/v1/retail/products/'.$this->productA->id.'/publish-online')->assertForbidden();
    }
}
