<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
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
 * signature invalide 401, tenant inconnu 404.
 *
 * Contrat réellement câblé (`RestaurantDeliveryAppWebhookController`,
 * aligné #8128 après la fusion des deux variantes RESTO-806) :
 *  - en-tête de signature `X-Leopardo-Delivery-Signature` (HMAC-SHA256 du
 *    corps brut ; secret = config `restaurantmanager.delivery_apps.
 *    uber_eats.webhook_secret`, à défaut dérivé déterministe de APP_KEY) ;
 *  - `company_id` au niveau racine du payload signé (résolution tenant) ;
 *  - `order.external_id` + `order.items[]` normalisés par `code` produit ;
 *  - réponse 201 (création) / 200 (rejeu idempotent), clé `da-<hash>`.
 */
class RestaurantDeliveryWebhookTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

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
     * Secret déterministe de l'adaptateur (aucun `webhook_secret` configuré
     * en environnement de test).
     */
    private function webhookSecret(): string
    {
        return hash_hmac('sha256', 'uber-eats:'.$this->companyA->id, (string) config('app.key'));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function webhookRequest(array $payload, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature ??= hash_hmac('sha256', $rawBody, $this->webhookSecret());

        return $this->call(
            'POST',
            '/api/v1/restaurant/webhooks/delivery-apps/uber_eats',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LEOPARDO_DELIVERY_SIGNATURE' => $signature],
            $rawBody,
        );
    }

    /** @return array<mixed> */
    private function validPayload(): array
    {
        $productCode = (string) RestaurantProduct::query()->value('code');

        return [
            'company_id' => (string) $this->companyA->id,
            'order' => [
                'external_id' => 'ext-order-1',
                'items' => [['code' => $productCode, 'quantity' => 2]],
                'customer' => ['name' => 'Client Uber', 'phone' => '+33600000000'],
            ],
        ];
    }

    public function test_webhook_creates_marketplace_order(): void
    {
        $response = $this->webhookRequest($this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.created', true);

        $order = RestaurantOrder::query()->where('reference', $response->json('data.reference'))->firstOrFail();

        $this->assertSame(OrderSource::DELIVERY_APP, $order->source);
        $this->assertStringStartsWith('da-', (string) $order->idempotency_key);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_webhook_replay_is_idempotent(): void
    {
        $payload = $this->validPayload();

        $first = $this->webhookRequest($payload)->assertStatus(201);
        $second = $this->webhookRequest($payload)->assertStatus(200)
            ->assertJsonPath('data.created', false);

        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertSame(1, RestaurantOrder::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_webhook_bad_signature_is_rejected(): void
    {
        $this->webhookRequest($this->validPayload(), str_repeat('0', 64))
            ->assertStatus(401);

        $this->assertSame(0, RestaurantOrder::query()->count());
    }

    public function test_webhook_unknown_restaurant_is_rejected(): void
    {
        $payload = $this->validPayload();
        // Tenant inexistant — le payload signé porte un company_id sans
        // correspondance (404 `company_not_found`).
        $payload['company_id'] = '00000000-0000-0000-0000-000000000042';

        // La signature doit être valide pour le company_id porté par le
        // payload (secret dérivé de CE company_id) — sinon le contrôle HMAC
        // rejette en 401 AVANT la résolution du tenant, masquant le 404.
        $unknownSecret = hash_hmac('sha256', 'uber-eats:'.$payload['company_id'], (string) config('app.key'));
        $signature = hash_hmac('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR), $unknownSecret);

        $this->webhookRequest($payload, $signature)->assertStatus(404);

        $this->assertSame(0, RestaurantOrder::query()->count());
    }
}
