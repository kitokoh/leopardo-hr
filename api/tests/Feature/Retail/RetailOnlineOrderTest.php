<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7808) — Commandes en ligne Leopardo Marche : checkout
 * invite (totaux serveur, idempotence, refus produit d'un autre vendeur),
 * suivi public par jeton (404 fail-closed), machine d'etats logistique
 * complete avec decrement/restauration du stock, RBAC vendeur
 * deny-by-default et isolation tenant.
 */
class RetailOnlineOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function enableShop(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->putJson('/api/v1/retail/online/settings', array_merge([
            'enabled' => true,
            'shop_name' => 'Boutique Test',
            'city' => 'Dakar',
            'currency' => 'XOF',
        ], $overrides))
            ->assertStatus(200)
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeLocation(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/locations', array_merge([
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-01',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * Cree un produit publie + visible en ligne.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeOnlineProduct(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        $product = $this->postJson('/api/v1/retail/products', array_merge([
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);

        return $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")
            ->assertStatus(200)
            ->json('data');
    }

    private function seedStock(Employee $actor, int $locationId, int $productId, float $quantity): void
    {
        $this->actingAsUser($actor);

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => $locationId,
            'product_id' => $productId,
            'quantity_delta' => $quantity,
            'reason_code' => 'purchase',
        ])->assertStatus(201);
    }

    private function levelQuantity(int $locationId, int $productId): string
    {
        /** @var RetailStockLevel $level */
        $level = RetailStockLevel::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->firstOrFail();

        return (string) $level->quantity;
    }

    /**
     * Corps de checkout invite valide (spec §3.2).
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkoutBody(string $sellerSlug, array $items, array $overrides = []): array
    {
        return array_merge([
            'seller' => $sellerSlug,
            'items' => $items,
            'customer' => [
                'name' => 'Awa Ndiaye',
                'phone' => '+221770000000',
                'email' => 'awa@example.test',
            ],
            'delivery' => [
                'address' => '12 rue des Manguiers',
                'city' => 'Dakar',
                'notes' => 'Appeler avant livraison',
            ],
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    /**
     * Passe un checkout invite public et retourne le payload de creation.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function checkout(array $body, int $expectedStatus = 201): array
    {
        return $this->postJson('/api/v1/public/market/orders', $body)
            ->assertStatus($expectedStatus)
            ->json('data');
    }

    /**
     * Recharge la commande du tenant A depuis sa reference publique.
     */
    private function orderByReference(string $reference): RetailOrder
    {
        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->where('reference', $reference)
            ->firstOrFail();

        return $order;
    }

    public function test_guest_checkout_computes_totals_server_side(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $productA = $this->storeOnlineProduct($this->principalA);
        $productB = $this->storeOnlineProduct($this->principalA, [
            'name' => 'Cafe Touba',
            'sku' => 'SKU-CAFE',
            'price_minor' => 700,
        ]);

        $data = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $productA['id'], 'quantity' => 2],
            ['product_id' => (int) $productB['id'], 'quantity' => 3],
        ]));

        // Total 100 % serveur : 2 x 1500 + 3 x 700 = 5100 (minor units).
        $this->assertSame(5_100, $data['total_minor']);
        $this->assertSame('XOF', $data['currency']);
        $this->assertSame($this->companyA->slug, $data['seller']);
        $this->assertStringStartsWith('WEB-', (string) $data['reference']);
        $this->assertSame(64, strlen((string) $data['tracking_token']));

        $order = $this->orderByReference((string) $data['reference']);

        $this->assertSame('online', $order->source->value);
        $this->assertSame('draft', $order->status->value);
        $this->assertSame('pending', $order->fulfillment_status?->value);
        $this->assertSame('Awa Ndiaye', $order->customer_name);
        $this->assertSame('Dakar', $order->delivery_city);
        $this->assertSame(2, $order->items()->count());
    }

    public function test_checkout_is_idempotent_per_seller_key(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        $body = $this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ]);

        $first = $this->checkout($body);

        // Rejeu meme cle → 200 avec EXACTEMENT le meme payload, pas de doublon.
        $replay = $this->checkout($body, 200);

        $this->assertSame($first, $replay);
        $this->assertSame(1, RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->count());
    }

    public function test_checkout_rejects_product_of_another_seller_and_unknown_seller(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $this->enableShop($this->principalB, ['shop_name' => 'Souk Casablanca', 'currency' => 'MAD']);
        $this->storeLocation($this->principalB, ['code' => 'STORE-B1']);

        $productB = $this->storeOnlineProduct($this->principalB, [
            'name' => 'Tajine artisanal',
            'sku' => 'SKU-TAJINE',
            'currency' => 'MAD',
        ]);

        // Produit du vendeur B commande chez le vendeur A → 422 (fail-closed).
        $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            (string) $this->companyA->slug,
            [['product_id' => (int) $productB['id'], 'quantity' => 1]],
        ))->assertStatus(422);

        $this->assertSame(0, RetailOrder::query()
            ->where('company_id', (string) $this->companyA->id)
            ->count());

        // Vendeur inconnu → 404 fail-closed.
        $this->postJson('/api/v1/public/market/orders', $this->checkoutBody(
            'unknown-shop',
            [['product_id' => (int) $productB['id'], 'quantity' => 1]],
        ))->assertStatus(404);
    }

    public function test_public_tracking_requires_valid_token(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        $order = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 2],
        ]));

        $reference = (string) $order['reference'];
        $token = (string) $order['tracking_token'];

        // Bon jeton : statut + timeline + lignes + total, DTO ferme (jamais
        // de company_id, de coordonnees client ni d'identifiants internes).
        $tracked = $this->getJson('/api/v1/public/market/orders/'.$reference.'?token='.$token)
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'pending')
            ->assertJsonPath('data.total_minor', 3_000)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.seller.slug', $this->companyA->slug);

        $this->assertSame(
            ['reference', 'fulfillment_status', 'total_minor', 'currency', 'seller', 'items', 'payment', 'timeline'],
            array_keys($tracked->json('data'))
        );
        $this->assertNotNull($tracked->json('data.timeline.placed_at'));

        // Jeton absent ou errone → 404 fail-closed (pas de probing).
        $this->getJson('/api/v1/public/market/orders/'.$reference)->assertStatus(404);
        $this->getJson('/api/v1/public/market/orders/'.$reference.'?token='.str_repeat('0', 64))
            ->assertStatus(404);
    }

    public function test_full_fulfillment_flow_decrements_then_restores_stock(): void
    {
        $this->enableShop($this->principalA);
        $location = $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 10.0);

        $order = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 3],
        ]));

        $orderId = (int) $this->orderByReference((string) $order['reference'])->id;

        // pending : le stock n'est PAS touche.
        $this->assertSame('10.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        // Confirmation → mouvements `sale`, statut historique completed.
        $this->actingAsUser($this->principalA);
        $confirmed = $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'confirmed')
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($confirmed->json('data.confirmed_at'));
        $this->assertSame('7.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        // Progression logistique complete.
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/ready")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'ready');

        $shipped = $this->postJson("/api/v1/retail/online/orders/{$orderId}/ship")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'shipped');
        $this->assertNotNull($shipped->json('data.shipped_at'));

        $delivered = $this->postJson("/api/v1/retail/online/orders/{$orderId}/deliver")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'delivered');
        $this->assertNotNull($delivered->json('data.delivered_at'));

        // Deuxieme commande : confirmation puis annulation → stock restaure
        // (mouvements `return`, meme voie que l'annulation POS).
        $second = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 2],
        ]));

        $secondId = (int) $this->orderByReference((string) $second['reference'])->id;

        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/online/orders/{$secondId}/confirm")->assertStatus(200);
        $this->assertSame('5.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        $this->postJson("/api/v1/retail/online/orders/{$secondId}/cancel", [
            'note' => 'Client injoignable',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'cancelled')
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('7.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_cancel_from_pending_does_not_touch_stock(): void
    {
        $this->enableShop($this->principalA);
        $location = $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 10.0);

        $order = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 4],
        ]));

        $orderId = (int) $this->orderByReference((string) $order['reference'])->id;

        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'cancelled');

        $this->assertSame('10.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_invalid_transitions_return_422_invalid_transition(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        $order = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ]));

        $orderId = (int) $this->orderByReference((string) $order['reference'])->id;

        $this->actingAsUser($this->principalA);

        // pending → ready / deliver : transitions interdites.
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/ready")
            ->assertStatus(422)
            ->assertJsonPath('errors.fulfillment_status.0', 'INVALID_TRANSITION');

        $this->postJson("/api/v1/retail/online/orders/{$orderId}/deliver")
            ->assertStatus(422)
            ->assertJsonPath('errors.fulfillment_status.0', 'INVALID_TRANSITION');

        // delivered est terminal : plus aucune transition.
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(200);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/ship")->assertStatus(200);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/deliver")->assertStatus(200);

        $this->postJson("/api/v1/retail/online/orders/{$orderId}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('errors.fulfillment_status.0', 'INVALID_TRANSITION');
    }

    public function test_vendor_rbac_and_tenant_isolation(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        $order = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ]));

        $orderId = (int) $this->orderByReference((string) $order['reference'])->id;

        // Employe simple : lecture OK, pilotage refuse (403 deny-by-default).
        $this->actingAsUser($this->employeeA);
        $this->getJson('/api/v1/retail/online/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.0.reference', (string) $order['reference']);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(403);
        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => false,
            'shop_name' => 'Boutique Test',
        ])->assertStatus(403);

        // Principal d'un AUTRE tenant : 404 fail-closed (jamais 403).
        $this->actingAsUser($this->principalB);
        $this->getJson("/api/v1/retail/online/orders/{$orderId}")->assertStatus(404);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(404);

        // La liste du tenant B ne voit pas la commande du tenant A.
        $this->getJson('/api/v1/retail/online/orders')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_online_orders_index_filters_by_fulfillment_status_and_source(): void
    {
        $this->enableShop($this->principalA);
        $this->storeLocation($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        $first = $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ]));

        $this->checkout($this->checkoutBody((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 2],
        ]));

        $firstId = (int) $this->orderByReference((string) $first['reference'])->id;

        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/online/orders/{$firstId}/confirm")->assertStatus(200);

        $this->getJson('/api/v1/retail/online/orders')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/retail/online/orders?fulfillment_status=confirmed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', (string) $first['reference']);

        // Filtre invalide → 422 (liste fermee d'etats).
        $this->getJson('/api/v1/retail/online/orders?fulfillment_status=bogus')->assertStatus(422);
    }
}
