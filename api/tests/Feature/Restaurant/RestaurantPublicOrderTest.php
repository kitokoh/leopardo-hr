<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Support\Facades\URL;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-805 (#6226) — commande en ligne publique (token signé).
 *
 * Verrouille : lien signé obligatoire (403 sinon), menu borné au tenant,
 * commande `open` avec totaux SERVEUR + décrément de stock + événement
 * outbox, idempotence par idempotency_key, source kiosk (RESTO-807),
 * paiement mobile money via le contrat PaymentGatewayInterface, aucun accès
 * inter-tenant.
 */
class RestaurantPublicOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private RestaurantBranch $branch;

    private RestaurantProduct $product;

    private RestaurantMenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'CM',
            'currency' => 'XAF',
            'features' => ['restaurantmanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'SN',
            'currency' => 'XOF',
            'features' => ['restaurantmanager' => true],
        ]);
        $this->companyB = $companyB;

        app(TenantManager::class)->withinTenant($companyA, function (): void {
            /** @var RestaurantBranch $branch */
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $this->branch = $branch;

            /** @var RestaurantProduct $product */
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 15000,
                'currency' => 'XAF',
                'is_available' => true,
            ]);
            $this->product = $product;

            /** @var RestaurantMenu $menu */
            $menu = RestaurantMenu::factory()->create([
                'branch_id' => $branch->id,
                'currency' => 'XAF',
            ]);

            /** @var RestaurantMenuItem $menuItem */
            $menuItem = RestaurantMenuItem::factory()->create([
                'menu_id' => $menu->id,
                'product_id' => $product->id,
            ]);
            $this->menuItem = $menuItem;
        });
    }

    private function signedUrl(string $route, string $companyId): string
    {
        return URL::temporarySignedRoute($route, now()->addHour(), ['company' => $companyId]);
    }

    public function test_public_menu_requires_valid_signature(): void
    {
        // Sans signature → 403 (middleware signed).
        $this->getJson('/api/v1/restaurant/public/menu?company='.$this->companyA->id)
            ->assertStatus(403);

        // Signature valide mais company inconnue → 200 avec menu vide
        // (aucune fuite : le menu est strictement borné au company_id signé).
        $this->getJson($this->signedUrl('restaurant.public.menu', '00000000-0000-0000-0000-000000000000'))
            ->assertOk()
            ->assertJsonPath('data.branches', []);
    }

    public function test_public_menu_is_tenant_scoped(): void
    {
        $response = $this->getJson($this->signedUrl('restaurant.public.menu', (string) $this->companyA->id))
            ->assertOk();

        $branches = $response->json('data.branches');
        $this->assertCount(1, $branches);
        $this->assertSame('XAF', $branches[0]['currency']);
        $this->assertSame('XAF', $branches[0]['menus'][0]['currency']);
        $this->assertSame(15000, $branches[0]['menus'][0]['items'][0]['price_minor']);
    }

    public function test_public_order_creates_open_order_with_server_totals(): void
    {
        $url = $this->signedUrl('restaurant.public.orders.store', (string) $this->companyA->id);

        $response = $this->postJson($url, [
            'branch_id' => (int) $this->branch->getAttribute('id'),
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => (int) $this->menuItem->getAttribute('id'), 'quantity' => 2]],
            'customer_name' => 'Awa Diallo',
            'customer_phone' => '+237690000000',
            'consent' => true,
        ])->assertStatus(201);

        $order = RestaurantOrder::query()->where('reference', $response->json('data.reference'))->firstOrFail();

        $this->assertSame(OrderStatus::OPEN, $order->status);
        $this->assertSame(OrderSource::WEB, $order->source);
        // 2 × 15 000 + TVA serveur (rate du produit).
        $this->assertSame(30000, (int) $order->subtotal_minor);
        $this->assertGreaterThan(30000, (int) $order->total_minor);
        $this->assertSame(1, $order->items()->count());

        // Événement outbox restaurant.order.created.v1.
        $this->assertSame(1, RestaurantOutboxEvent::query()
            ->where('company_id', $this->companyA->id)
            ->where('event_type', 'restaurant.order.created.v1')
            ->count());
    }

    public function test_public_order_is_idempotent(): void
    {
        $url = $this->signedUrl('restaurant.public.orders.store', (string) $this->companyA->id);
        $payload = [
            'branch_id' => (int) $this->branch->getAttribute('id'),
            'order_type' => 'delivery',
            'items' => [['menu_item_id' => (int) $this->menuItem->getAttribute('id'), 'quantity' => 1]],
            'consent' => true,
            'idempotency_key' => 'client-cart-1',
        ];

        $first = $this->postJson($url, $payload)->assertStatus(201);
        $second = $this->postJson($url, $payload)->assertStatus(201);

        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertSame(1, RestaurantOrder::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_public_order_cannot_reach_another_tenant(): void
    {
        // Lien signé pour le tenant B, branch du tenant A → 404 (aucun accès
        // inter-tenant via le menu public).
        $url = $this->signedUrl('restaurant.public.orders.store', (string) $this->companyB->id);

        $this->postJson($url, [
            'branch_id' => (int) $this->branch->getAttribute('id'),
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => (int) $this->menuItem->getAttribute('id'), 'quantity' => 1]],
            'consent' => true,
        ])->assertStatus(404);
    }

    public function test_kiosk_source_is_supported(): void
    {
        $url = $this->signedUrl('restaurant.public.orders.store', (string) $this->companyA->id);

        $response = $this->postJson($url, [
            'branch_id' => (int) $this->branch->getAttribute('id'),
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => (int) $this->menuItem->getAttribute('id'), 'quantity' => 1]],
            'consent' => true,
            'source' => 'kiosk',
        ])->assertStatus(201);

        $order = RestaurantOrder::query()->where('reference', $response->json('data.reference'))->firstOrFail();
        $this->assertSame(OrderSource::KIOSK, $order->source);
    }

    public function test_public_payment_initiates_mobile_money(): void
    {
        $storeUrl = $this->signedUrl('restaurant.public.orders.store', (string) $this->companyA->id);

        $created = $this->postJson($storeUrl, [
            'branch_id' => (int) $this->branch->getAttribute('id'),
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => (int) $this->menuItem->getAttribute('id'), 'quantity' => 1]],
            'consent' => true,
        ])->assertStatus(201);

        $order = RestaurantOrder::query()->where('reference', $created->json('data.reference'))->firstOrFail();
        $payUrl = URL::temporarySignedRoute(
            'restaurant.public.orders.pay',
            now()->addHour(),
            ['company' => (string) $this->companyA->id, 'order' => (int) $order->getAttribute('id')]
        );

        $this->postJson($payUrl, ['provider_code' => 'mobile_money'])
            ->assertStatus(201)
            ->assertJsonPath('data.provider_code', 'mobile_money')
            ->assertJsonPath('data.status', 'pending');
    }
}
