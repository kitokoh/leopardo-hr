<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7814) — Comptes acheteurs marketplace Leopardo Marche :
 * inscription/connexion (jeton opaque plateforme), liaison
 * commande ↔ buyer au checkout, historique cross-tenant, favoris CRUD,
 * avis verifies post-livraison (refus si non livre, unicite par
 * commande/produit), agregats de notation publics et isolation entre
 * buyers. DTO publics stricts : jamais de company_id ni de mot de passe.
 */
class RetailMarketBuyerAccountTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

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
     */
    private function enableShop(Employee $actor, array $overrides = []): void
    {
        $this->actingAsUser($actor);

        $this->putJson('/api/v1/retail/online/settings', array_merge([
            'enabled' => true,
            'shop_name' => 'Boutique Test',
            'city' => 'Dakar',
            'currency' => 'XOF',
        ], $overrides))->assertStatus(200);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeOnlineProduct(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique '.Str::random(6),
            'code' => 'STORE-'.Str::upper(Str::random(6)),
        ])->assertStatus(201);

        /** @var array<string, mixed> $product */
        $product = $this->postJson('/api/v1/retail/products', array_merge([
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        return $product;
    }

    /**
     * Inscription d'un buyer — retourne [token, email].
     *
     * @return array{0: string, 1: string}
     */
    private function registerBuyer(string $name = 'Awa Ndiaye'): array
    {
        $email = Str::lower(Str::random(10)).'@buyer.test';

        /** @var array<string, mixed> $data */
        $data = $this->postJson('/api/v1/public/market/account/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'Buyer-S3cret-2026!',
            'phone' => '+221770000000',
        ])->assertStatus(201)->json('data');

        return [(string) $data['token'], $email];
    }

    /**
     * Checkout public, optionnellement authentifie buyer.
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function checkout(string $sellerSlug, array $items, ?string $buyerToken = null): array
    {
        $headers = $buyerToken !== null ? ['Authorization' => 'Bearer '.$buyerToken] : [];

        return $this->postJson('/api/v1/public/market/orders', [
            'seller' => $sellerSlug,
            'items' => $items,
            'customer' => [
                'name' => 'Awa Ndiaye',
                'phone' => '+221770000000',
            ],
            'delivery' => [
                'address' => '12 rue des Manguiers',
                'city' => 'Dakar',
            ],
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ], $headers)->assertStatus(201)->json('data');
    }

    private function markDelivered(string $reference): void
    {
        RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', $reference)
            ->update([
                'fulfillment_status' => RetailFulfillmentStatus::Delivered->value,
                'delivered_at' => now(),
            ]);
    }

    public function test_register_login_me_logout_flow(): void
    {
        [$token, $email] = $this->registerBuyer();

        // me avec jeton valide → profil public, sans mot de passe.
        $me = $this->getJson('/api/v1/public/market/account/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200)
            ->json('data');
        $this->assertSame('Awa Ndiaye', $me['name']);
        $this->assertSame($email, $me['email']);
        $this->assertArrayNotHasKey('password', $me);
        $this->assertArrayNotHasKey('id', $me);

        // Email deja pris → 422 (unicite).
        $this->postJson('/api/v1/public/market/account/register', [
            'name' => 'Doublon',
            'email' => $email,
            'password' => 'Buyer-S3cret-2026!',
        ])->assertStatus(422);

        // Mauvais mot de passe → 401 uniforme.
        $this->postJson('/api/v1/public/market/account/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        // Login OK → nouveau jeton utilisable.
        $login = $this->postJson('/api/v1/public/market/account/login', [
            'email' => $email,
            'password' => 'Buyer-S3cret-2026!',
        ])->assertStatus(200)->json('data');
        $this->assertIsString($login['token']);

        // Logout → le jeton revoque ne passe plus (401 fail-closed).
        $this->postJson('/api/v1/public/market/account/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);
        $this->getJson('/api/v1/public/market/account/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);

        // Sans jeton / jeton fantaisiste → 401.
        $this->getJson('/api/v1/public/market/account/me')->assertStatus(401);
        $this->getJson('/api/v1/public/market/account/me', ['Authorization' => 'Bearer mkb_'.str_repeat('0', 64)])
            ->assertStatus(401);
    }

    public function test_checkout_links_order_to_authenticated_buyer(): void
    {
        $this->enableShop($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        [$token] = $this->registerBuyer();

        // Checkout AUTHENTIFIE → commande liee au buyer.
        $linked = $this->checkout((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 2],
        ], $token);

        /** @var MarketplaceBuyer $buyer */
        $buyer = MarketplaceBuyer::query()->firstOrFail();

        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', (string) $linked['reference'])
            ->firstOrFail();
        $this->assertSame((int) $buyer->id, $order->buyer_id);

        // Checkout invite (sans jeton) → buyer_id null.
        $guest = $this->checkout((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ]);

        /** @var RetailOrder $guestOrder */
        $guestOrder = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', (string) $guest['reference'])
            ->firstOrFail();
        $this->assertNull($guestOrder->buyer_id);

        // Jeton invalide → jamais bloquant, commande invitee.
        $invalid = $this->checkout((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ], 'mkb_'.str_repeat('f', 64));

        /** @var RetailOrder $invalidOrder */
        $invalidOrder = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', (string) $invalid['reference'])
            ->firstOrFail();
        $this->assertNull($invalidOrder->buyer_id);
    }

    public function test_account_orders_history_is_cross_tenant_and_isolated(): void
    {
        $this->enableShop($this->principalA);
        $this->enableShop($this->principalB, ['shop_name' => 'Boutique B', 'city' => 'Rabat', 'currency' => 'MAD']);
        $productA = $this->storeOnlineProduct($this->principalA);
        $productB = $this->storeOnlineProduct($this->principalB, ['currency' => 'MAD', 'price_minor' => 900]);

        [$token] = $this->registerBuyer();
        [$otherToken] = $this->registerBuyer('Moussa Ba');

        $this->checkout((string) $this->companyA->slug, [
            ['product_id' => (int) $productA['id'], 'quantity' => 2],
        ], $token);
        $this->checkout((string) $this->companyB->slug, [
            ['product_id' => (int) $productB['id'], 'quantity' => 1],
        ], $token);

        $payload = $this->getJson('/api/v1/public/market/account/orders', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200)
            ->json();

        /** @var list<array<string, mixed>> $orders */
        $orders = $payload['data'];
        $this->assertCount(2, $orders);

        $sellers = array_map(static fn (array $order): string => (string) $order['seller']['name'], $orders);
        $this->assertContains('Boutique Test', $sellers);
        $this->assertContains('Boutique B', $sellers);

        foreach ($orders as $order) {
            // DTO public strict : jamais de company_id ni d'id interne.
            $this->assertArrayNotHasKey('company_id', $order);
            $this->assertArrayNotHasKey('id', $order);
            $this->assertStringStartsWith('WEB-', (string) $order['reference']);
            $this->assertSame(64, strlen((string) $order['tracking_token']));
            $this->assertSame('pending', $order['fulfillment_status']);
            $this->assertNotEmpty($order['items']);
        }

        // Isolation : un autre buyer ne voit RIEN.
        $other = $this->getJson('/api/v1/public/market/account/orders', ['Authorization' => 'Bearer '.$otherToken])
            ->assertStatus(200)
            ->json('data');
        $this->assertSame([], $other);

        // Sans jeton → 401.
        $this->getJson('/api/v1/public/market/account/orders')->assertStatus(401);
    }

    public function test_favorites_crud_and_isolation(): void
    {
        $this->enableShop($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);
        $productId = (int) $product['id'];

        [$token] = $this->registerBuyer();
        [$otherToken] = $this->registerBuyer('Moussa Ba');

        // Sans jeton → 401 fail-closed.
        $this->postJson('/api/v1/public/market/account/favorites', ['product_id' => $productId])
            ->assertStatus(401);

        // Produit inconnu → 404 fail-closed.
        $this->postJson('/api/v1/public/market/account/favorites', ['product_id' => 999_999], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(404);

        // Ajout → 201 ; rejeu idempotent → 200.
        $this->postJson('/api/v1/public/market/account/favorites', ['product_id' => $productId], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(201);
        $this->postJson('/api/v1/public/market/account/favorites', ['product_id' => $productId], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);

        // Liste → DTO produit public complet, sans company_id.
        $favorites = $this->getJson('/api/v1/public/market/account/favorites', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(1, $favorites);
        $this->assertSame($productId, $favorites[0]['id']);
        $this->assertSame('Boutique Test', $favorites[0]['seller']['name']);
        $this->assertArrayNotHasKey('company_id', $favorites[0]);
        $this->assertSame(0, $favorites[0]['rating_count']);

        // Isolation : l'autre buyer a une liste vide.
        $this->assertSame([], $this->getJson('/api/v1/public/market/account/favorites', ['Authorization' => 'Bearer '.$otherToken])
            ->assertStatus(200)
            ->json('data'));

        // Produit depublie → filtre fail-closed de la liste.
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/products/{$productId}/unpublish-online")->assertStatus(200);
        $this->assertSame([], $this->getJson('/api/v1/public/market/account/favorites', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200)
            ->json('data'));
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/products/{$productId}/publish-online")->assertStatus(200);

        // Retrait idempotent → liste vide.
        $this->deleteJson('/api/v1/public/market/account/favorites/'.$productId, [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);
        $this->assertSame([], $this->getJson('/api/v1/public/market/account/favorites', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200)
            ->json('data'));
    }

    public function test_review_rejected_until_order_is_delivered(): void
    {
        $this->enableShop($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);

        [$token] = $this->registerBuyer();
        [$otherToken] = $this->registerBuyer('Moussa Ba');

        $order = $this->checkout((string) $this->companyA->slug, [
            ['product_id' => (int) $product['id'], 'quantity' => 1],
        ], $token);
        $reference = (string) $order['reference'];

        // Commande encore `pending` → refus 422 ORDER_NOT_DELIVERED.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => $reference,
            'product_id' => (int) $product['id'],
            'rating' => 5,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonPath('errors.order_reference.0', 'ORDER_NOT_DELIVERED');

        $this->markDelivered($reference);

        // Produit absent de la commande → 422 PRODUCT_NOT_IN_ORDER.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => $reference,
            'product_id' => 999_999,
            'rating' => 4,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonPath('errors.product_id.0', 'PRODUCT_NOT_IN_ORDER');

        // Commande d'un AUTRE buyer → 404 fail-closed (pas de probing).
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => $reference,
            'product_id' => (int) $product['id'],
            'rating' => 5,
        ], ['Authorization' => 'Bearer '.$otherToken])
            ->assertStatus(404);

        // Sans jeton → 401.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => $reference,
            'product_id' => (int) $product['id'],
            'rating' => 5,
        ])->assertStatus(401);
    }

    public function test_review_accepted_after_delivery_with_public_aggregates(): void
    {
        $this->enableShop($this->principalA);
        $product = $this->storeOnlineProduct($this->principalA);
        $productId = (int) $product['id'];

        [$token] = $this->registerBuyer();
        [$otherToken] = $this->registerBuyer('Moussa Ba');

        $orderA = $this->checkout((string) $this->companyA->slug, [
            ['product_id' => $productId, 'quantity' => 1],
        ], $token);
        $orderB = $this->checkout((string) $this->companyA->slug, [
            ['product_id' => $productId, 'quantity' => 2],
        ], $otherToken);

        $this->markDelivered((string) $orderA['reference']);
        $this->markDelivered((string) $orderB['reference']);

        // Avis verifie → 201 auto-approve.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => (string) $orderA['reference'],
            'product_id' => $productId,
            'rating' => 5,
            'comment' => 'Excellent, livre rapidement.',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'approved');

        // Anti-abus : 1 avis par produit par commande → 422 ALREADY_REVIEWED.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => (string) $orderA['reference'],
            'product_id' => $productId,
            'rating' => 1,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonPath('errors.product_id.0', 'ALREADY_REVIEWED');

        // Commentaire trop long → 422 (longueur max anti-abus).
        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => (string) $orderB['reference'],
            'product_id' => $productId,
            'rating' => 4,
            'comment' => str_repeat('a', 1_001),
        ], ['Authorization' => 'Bearer '.$otherToken])->assertStatus(422);

        $this->postJson('/api/v1/public/market/account/reviews', [
            'order_reference' => (string) $orderB['reference'],
            'product_id' => $productId,
            'rating' => 4,
        ], ['Authorization' => 'Bearer '.$otherToken])->assertStatus(201);

        // Liste publique des avis : approuves, pagines, nom PUBLIC (prenom
        // + initiale), jamais d'email ni de company_id.
        $reviews = $this->getJson("/api/v1/public/market/products/{$productId}/reviews")
            ->assertStatus(200)
            ->json();
        $this->assertSame(2, $reviews['meta']['rating_count']);
        $this->assertSame(4.5, $reviews['meta']['rating_avg']);
        $names = array_map(static fn (array $review): string => (string) $review['buyer_name'], $reviews['data']);
        $this->assertContains('Awa N.', $names);
        $this->assertContains('Moussa B.', $names);
        foreach ($reviews['data'] as $review) {
            $this->assertArrayNotHasKey('company_id', $review);
            $this->assertArrayNotHasKey('email', $review);
            $this->assertArrayNotHasKey('buyer_id', $review);
        }

        // Agregats exposes dans les DTO publics produit + boutique.
        $productPayload = $this->getJson('/api/v1/public/market/products/'.$productId)
            ->assertStatus(200)
            ->json('data');
        $this->assertSame(4.5, $productPayload['rating_avg']);
        $this->assertSame(2, $productPayload['rating_count']);

        $sellers = $this->getJson('/api/v1/public/market/sellers')
            ->assertStatus(200)
            ->json('data');
        $this->assertSame(4.5, $sellers[0]['rating_avg']);
        $this->assertSame(2, $sellers[0]['rating_count']);

        // Avis d'un produit inconnu → 404 fail-closed.
        $this->getJson('/api/v1/public/market/products/999999/reviews')->assertStatus(404);
    }
}
