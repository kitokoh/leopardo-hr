<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-050 (#8208, tranche 7) — Suivi public des commandes restaurant :
 * référence non énumérable + SECRET (hash SHA-256 stocké, clair présenté
 * une seule fois à la création), aligné sur l'invariant des autres
 * verticales publiques.
 *
 * Couvre, sur les 3 surfaces invitées (boutique par jeton, kiosque, page
 * par slug) :
 *  - le secret est retourné UNE FOIS à la création réelle (jamais au
 *    rejeu idempotent) et seul son hash est persisté ;
 *  - le suivi d'une commande récente EXIGE le secret — référence inconnue,
 *    secret absent et secret invalide produisent le MÊME 404
 *    (anti-énumération) ;
 *  - transition : une commande antérieure (hash NULL) reste suivie par
 *    l'ancien flux (référence seule) pendant la fenêtre de 90 j, avec
 *    en-têtes `Deprecation` / `Sunset`.
 */
class RestaurantPublicTrackingSecretTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    /**
     * @return array{token: string, branch: RestaurantBranch, product: RestaurantProduct}
     */
    private function shopContext(Company $company): array
    {
        $plain = 'rshop_'.bin2hex(random_bytes(20));

        return app(TenantManager::class)->withinTenant($company, function () use ($plain, $company): array {
            RestaurantPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => RestaurantPublicShopToken::hash($plain),
                'name' => 'default',
                'active' => true,
            ]);

            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => 'XAF',
                'tax_rate_id' => null,
                'is_available' => true,
            ]);

            return ['token' => $plain, 'branch' => $branch, 'product' => $product];
        });
    }

    private function makePublicBranch(Company $company, string $slug): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create([
                'is_public' => true,
                'status' => 'active',
                'currency' => 'XAF',
                'public_slug' => $slug,
            ])
        );
    }

    private function makePublishedProduct(Company $company, RestaurantBranch $branch, string $code): RestaurantProduct
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantProduct => RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'code' => $code,
                'price_minor' => 2500,
                'currency' => 'XAF',
                'tax_rate_id' => null,
                'is_available' => true,
                'is_published_online' => true,
                'status' => 'active',
            ])
        );
    }

    // ── Boutique par jeton (RESTO-805) ─────────────────────────────────────

    public function test_shop_creation_presents_tracking_secret_once_and_stores_only_its_hash(): void
    {
        $company = $this->makeCompany();
        $ctx = $this->shopContext($company);

        $payload = [
            'branch_id' => $ctx['branch']->id,
            'idempotency_key' => 'secret-'.bin2hex(random_bytes(8)),
            'items' => [['product_code' => $ctx['product']->code, 'quantity' => 1]],
        ];

        $created = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/shop/orders', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.created', true);

        $secret = $created->json('data.tracking_secret');
        $reference = $created->json('data.reference');

        assert(is_string($secret) && is_string($reference));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);

        // Seul le hash SHA-256 est persisté — jamais le clair.
        $storedHash = app(TenantManager::class)->withinTenant(
            $company,
            fn (): mixed => RestaurantOrder::query()->where('reference', $reference)->value('tracking_secret_hash')
        );
        assert(is_string($storedHash));
        $this->assertSame(hash('sha256', $secret), $storedHash);
        $this->assertNotSame($secret, $storedHash);

        // Rejeu idempotent : même commande, mais le secret n'est PAS représenté.
        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/shop/orders', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.tracking_secret', null);
    }

    public function test_shop_track_requires_secret_for_new_orders(): void
    {
        $company = $this->makeCompany();
        $ctx = $this->shopContext($company);

        $created = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/shop/orders', [
                'branch_id' => $ctx['branch']->id,
                'items' => [['product_code' => $ctx['product']->code, 'quantity' => 1]],
            ])
            ->assertStatus(201);

        $reference = $created->json('data.reference');
        $secret = $created->json('data.tracking_secret');

        assert(is_string($reference) && is_string($secret));

        // Anti-énumération : référence inconnue, secret absent et secret
        // invalide produisent le MÊME 404.
        $unknown = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/shop/orders/RST-INCONNU0')
            ->assertNotFound();

        $missing = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/shop/orders/'.$reference)
            ->assertNotFound();

        $wrong = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/shop/orders/'.$reference.'?secret='.str_repeat('0', 64))
            ->assertNotFound();

        $this->assertSame($unknown->getContent(), $missing->getContent());
        $this->assertSame($unknown->getContent(), $wrong->getContent());

        // Query `?secret=` ET en-tête `X-Tracking-Secret` sont acceptés.
        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/shop/orders/'.$reference.'?secret='.$secret)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertHeaderMissing('Deprecation');

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->withHeader('X-Tracking-Secret', $secret)
            ->getJson('/api/v1/public/restaurant/shop/orders/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);
    }

    public function test_shop_track_legacy_order_without_hash_is_deprecated_but_served(): void
    {
        $company = $this->makeCompany();
        $ctx = $this->shopContext($company);

        // Commande « antérieure » : aucun hash de secret (flux legacy).
        $order = app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantOrder => RestaurantOrder::factory()->create([
                'branch_id' => $ctx['branch']->id,
                'tracking_secret_hash' => null,
            ])
        );

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/shop/orders/'.$order->reference)
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference)
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Sunset', 'Mon, 28 Dec 2026 00:00:00 GMT');
    }

    // ── Kiosque (RESTO-807) ────────────────────────────────────────────────

    public function test_kiosk_track_requires_secret_for_new_orders(): void
    {
        $company = $this->makeCompany();
        $ctx = $this->shopContext($company);

        $created = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->postJson('/api/v1/public/restaurant/kiosk/orders', [
                'branch_id' => $ctx['branch']->id,
                'items' => [['product_code' => $ctx['product']->code, 'quantity' => 1]],
            ])
            ->assertStatus(201);

        $reference = $created->json('data.reference');
        $secret = $created->json('data.tracking_secret');

        assert(is_string($reference) && is_string($secret));

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/kiosk/orders/'.$reference)
            ->assertNotFound();

        $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
            ->getJson('/api/v1/public/restaurant/kiosk/orders/'.$reference.'?secret='.$secret)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);
    }

    // ── Page publique par slug (RESTO-902) ─────────────────────────────────

    public function test_slug_creation_presents_tracking_secret_and_track_requires_it(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makePublicBranch($company, 'chez-secret');
        $product = $this->makePublishedProduct($company, $branch, 'PLAT-SECRET');

        $created = $this->postJson('/api/v1/public/restaurants/chez-secret/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [['product_code' => $product->code, 'quantity' => 1]],
        ])->assertCreated();

        $reference = $created->json('data.reference');
        $secret = $created->json('data.tracking_secret');

        assert(is_string($reference) && is_string($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);

        // Sans secret → 404 (identique à une référence inconnue).
        $this->getJson('/api/v1/public/restaurants/chez-secret/orders/'.$reference)
            ->assertNotFound();

        $this->getJson('/api/v1/public/restaurants/chez-secret/orders/'.$reference.'?secret='.str_repeat('1', 64))
            ->assertNotFound();

        $this->getJson('/api/v1/public/restaurants/chez-secret/orders/'.$reference.'?secret='.$secret)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertHeaderMissing('Deprecation');
    }

    public function test_slug_track_legacy_order_without_hash_is_deprecated_but_served(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makePublicBranch($company, 'chez-legacy');

        // Commande « antérieure » rattachée à LA branche du slug, sans hash.
        $order = app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantOrder => RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'tracking_secret_hash' => null,
            ])
        );

        $this->getJson('/api/v1/public/restaurants/chez-legacy/orders/'.$order->reference)
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference)
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Sunset', 'Mon, 28 Dec 2026 00:00:00 GMT');
    }
}
