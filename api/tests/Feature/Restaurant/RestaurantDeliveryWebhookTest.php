<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryAppConfig;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-806 (#6227) — intégrations des apps de livraison (webhooks).
 *
 * Verrouille : webhook signé HMAC → commande marketplace avec le MÊME
 * workflow interne (source delivery_app), rejeu idempotent (un seul ordre),
 * signature invalide 401, restaurant marketplace inconnu 404.
 */
class RestaurantDeliveryWebhookTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private string $secret = 'webhook-secret-test';

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

        app(TenantManager::class)->withinTenant($companyA, function (): void {
            RestaurantDeliveryAppConfig::query()->create([
                'company_id' => $companyA->id,
                'provider' => RestaurantDeliveryAppConfig::PROVIDER_UBER_EATS,
                'enabled' => true,
                'external_restaurant_id' => 'ext-resto-1',
                'webhook_secret_encrypted' => $this->secret,
            ]);

            /** @var RestaurantBranch $branch */
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);

            /** @var RestaurantProduct $product */
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 12000,
                'currency' => 'XAF',
                'is_available' => true,
            ]);

            /** @var RestaurantMenu $menu */
            $menu = RestaurantMenu::factory()->create(['branch_id' => $branch->id, 'currency' => 'XAF']);

            RestaurantMenuItem::factory()->create([
                'menu_id' => $menu->id,
                'product_id' => $product->id,
            ]);
        });
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function webhookRequest(array $payload): \Illuminate\Testing\TestResponse
    {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        return $this->postJson(
            '/api/v1/restaurant/webhooks/delivery-apps/uber_eats',
            $payload,
            ['X-Signature' => $signature]
        );
    }

    private function validPayload(): array
    {
        $productId = (int) RestaurantProduct::query()->value('id');

        return [
            'order_id' => 'ext-order-1',
            'restaurant_id' => 'ext-resto-1',
            'items' => [['product_id' => $productId, 'quantity' => 2]],
            'customer' => ['name' => 'Client Uber', 'phone' => '+33600000000'],
            'delivery' => ['address' => '12 rue X'],
            'note' => 'Sans oignons',
        ];
    }

    public function test_webhook_creates_marketplace_order(): void
    {
        $response = $this->webhookRequest($this->validPayload())
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'received');

        $order = RestaurantOrder::query()->where('reference', $response->json('data.order_reference'))->firstOrFail();

        $this->assertSame(OrderSource::DELIVERY_APP, $order->source);
        $this->assertSame('delivery-uber_eats-ext-order-1', $order->idempotency_key);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_webhook_replay_is_idempotent(): void
    {
        $payload = $this->validPayload();

        $first = $this->webhookRequest($payload)->assertStatus(202);
        $second = $this->webhookRequest($payload)->assertStatus(202);

        $this->assertSame($first->json('data.order_reference'), $second->json('data.order_reference'));
        $this->assertSame(1, RestaurantOrder::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_webhook_bad_signature_is_rejected(): void
    {
        $this->postJson(
            '/api/v1/restaurant/webhooks/delivery-apps/uber_eats',
            $this->validPayload(),
            ['X-Signature' => str_repeat('0', 64)]
        )->assertStatus(401);

        $this->assertSame(0, RestaurantOrder::query()->count());
    }

    public function test_webhook_unknown_restaurant_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['restaurant_id'] = 'ext-resto-inconnu';
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        $this->postJson(
            '/api/v1/restaurant/webhooks/delivery-apps/uber_eats',
            $payload,
            ['X-Signature' => $signature]
        )->assertStatus(404);

        $this->assertSame(0, RestaurantOrder::query()->count());
    }
}
