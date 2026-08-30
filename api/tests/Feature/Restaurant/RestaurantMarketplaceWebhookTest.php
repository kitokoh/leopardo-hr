<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMarketplaceEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-806 (#6227) — Webhooks entrants des apps de livraison.
 *
 * Couvre : signature invalide → 401 (fail-closed), provider inconnu → 404,
 * commande marketplace → même workflow interne (source delivery_app, totaux
 * serveur), idempotence par event_id (rejeu → aucune commande dupliquée),
 * rapprochement produit par code interne.
 */
class RestaurantMarketplaceWebhookTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SECRET = 'test-webhook-secret';

    private function makeTenant(): array
    {
        config(['restaurantmanager.marketplace.uber_eats.webhook_secret' => self::SECRET]);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        $plainToken = 'rshop_marketplace_test';

        $data = app(TenantManager::class)->withinTenant($company, function () use ($plainToken): array {
            RestaurantPublicShopToken::query()->create([
                'company_id' => currentCompany()->id,
                'token_hash' => RestaurantPublicShopToken::hash($plainToken),
                'name' => 'marketplace test',
                'active' => true,
            ]);

            $branch = RestaurantBranch::factory()->create(['code' => 'MAIN']);
            $category = RestaurantCategory::factory()->create(['name' => 'Plats']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => null,
                'category_id' => $category->id,
                'code' => 'BURGER-XL',
                'price_minor' => 3500,
                'currency' => 'XAF',
                'is_available' => true,
                'status' => 'active',
            ]);

            return [
                'company' => currentCompany(),
                'branch' => $branch,
                'product' => $product,
            ];
        });

        $data['plain_token'] = $plainToken;

        return $data;
    }

    private function uberPayload(string $eventId = 'uber-order-42', int $qty = 1): array
    {
        return [
            'meta' => [
                'placed_order_id' => $eventId,
                'place_code' => 'MAIN',
            ],
            'customer' => [
                'name' => 'Jean Dupont',
                'phone' => '+237600000000',
            ],
            'order_items' => [
                [
                    'id' => 'BURGER-XL',
                    'title' => 'Burger XL',
                    'quantity' => $qty,
                    'price' => 35.0,
                ],
            ],
            'currency_code' => 'XAF',
            'order_note' => 'Sans oignons',
        ];
    }

    private function signedCall(array $payload, string $token): \Illuminate\Testing\TestResponse
    {
        $raw = (string) json_encode($payload);
        $signature = hash_hmac('sha256', $raw, self::SECRET);

        return $this->withHeader('X-Uber-Signature', $signature)
            ->postJson('/api/v1/restaurant/marketplace/uber_eats/webhooks?token='.$token, $payload);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $tenant = $this->makeTenant();

        $this->withHeader('X-Uber-Signature', 'deadbeef')
            ->postJson('/api/v1/restaurant/marketplace/uber_eats/webhooks?token='.$tenant['plain_token'], $this->uberPayload())
            ->assertStatus(401);
    }

    public function test_webhook_rejects_unknown_provider(): void
    {
        $tenant = $this->makeTenant();
        $raw = (string) json_encode($this->uberPayload());

        $this->withHeader('X-Deliveroo-Signature', hash_hmac('sha256', $raw, self::SECRET))
            ->postJson('/api/v1/restaurant/marketplace/deliveroo/webhooks?token='.$tenant['plain_token'], $this->uberPayload())
            ->assertStatus(404);
    }

    public function test_webhook_creates_order_with_internal_workflow(): void
    {
        $tenant = $this->makeTenant();

        $this->signedCall($this->uberPayload(), $tenant['plain_token'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.order_reference', fn ($ref) => str_starts_with((string) $ref, 'RST-'));

        $this->assertDatabaseHas('restaurant_orders', [
            'source' => 'delivery_app',
            'order_type' => 'delivery',
            'branch_id' => $tenant['branch']->id,
            'subtotal_minor' => 3500,
            'currency' => 'XAF',
            'status' => 'draft',
        ]);

        // Le même workflow interne : transition → paiement possibles.
        $order = app(TenantManager::class)->withinTenant($tenant['company'], function () {
            return \App\Modules\RestaurantManager\Domain\Models\RestaurantOrder::query()
                ->where('source', 'delivery_app')
                ->firstOrFail();
        });

        $this->assertSame('delivery_app', $order->source->value);
        $this->assertCount(1, app(TenantManager::class)->withinTenant($tenant['company'], fn () => $order->items));
    }

    public function test_webhook_replay_is_idempotent(): void
    {
        $tenant = $this->makeTenant();

        $this->signedCall($this->uberPayload(), $tenant['plain_token'])->assertStatus(201);
        $this->signedCall($this->uberPayload(), $tenant['plain_token'])->assertStatus(200)
            ->assertJsonPath('data.status', 'replayed');

        $this->assertDatabaseCount('restaurant_orders', 1);
        $this->assertDatabaseCount('restaurant_marketplace_events', 1);
    }

    public function test_webhook_fails_when_product_code_unknown(): void
    {
        $tenant = $this->makeTenant();
        $payload = $this->uberPayload();
        $payload['order_items'][0]['id'] = 'INCONNU-99';

        $this->signedCall($payload, $tenant['plain_token'])
            ->assertStatus(422);

        $this->assertDatabaseCount('restaurant_orders', 0);
        $this->assertDatabaseHas('restaurant_marketplace_events', [
            'status' => 'failed',
        ]);
    }

    public function test_marketplace_event_audit_is_recorded(): void
    {
        $tenant = $this->makeTenant();

        $this->signedCall($this->uberPayload('uber-order-777'), $tenant['plain_token'])->assertStatus(201);

        $this->assertDatabaseHas('restaurant_marketplace_events', [
            'provider' => 'uber_eats',
            'event_id' => 'uber-order-777',
            'status' => 'processed',
        ]);
    }
}
