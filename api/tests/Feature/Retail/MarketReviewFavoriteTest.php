<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Models\MarketReview;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7814 — Favoris + avis & notations MODÉRÉS de Leopardo Marché.
 *
 * Critères d'acceptation couverts :
 * - favoris : ajout idempotent d'une cible PUBLIQUE (404 fail-closed sinon),
 *   liste bornée au compte, retrait borné au compte ;
 * - avis vérifiés : dépôt refusé sans commande LIVRÉE (422), créé `pending`
 *   (PAS public), un avis par cible et par compte ;
 * - modération : approve → public (note moyenne), reject terminal, RBAC
 *   principal/rh, avis d'un AUTRE vendeur → 404 (isolation tenant par
 *   valeur), transitions invalides → 422 INVALID_TRANSITION.
 */
class MarketReviewFavoriteTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'marche-secret-2026';

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $principalB;

    private Employee $employeeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = $this->sellerCompany('SN', 'XOF');
        $this->companyB = $this->sellerCompany('MA', 'MAD');
        $this->principalA = $this->manager($this->companyA, 'principal');
        $this->principalB = $this->manager($this->companyB, 'principal');
        $this->employeeA = $this->manager($this->companyA, 'employee');
    }

    private function sellerCompany(string $country, string $currency): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);
        $company->setFeature('retail', true);
        $company->save();

        return $company;
    }

    private function manager(Company $company, string $managerRole): Employee
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
     * @return array{product_id: int, slug: string}
     */
    private function publicProduct(Employee $actor, Company $company, string $sku, string $currency): array
    {
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => true,
            'shop_name' => 'Boutique '.$sku,
            'city' => 'Dakar',
            'currency' => $currency,
        ])->assertStatus(200);

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit '.$sku,
            'sku' => $sku,
            'price_minor' => 2_000,
            'currency' => $currency,
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        $location = $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique '.$sku,
            'code' => 'STORE-'.$sku,
        ])->assertStatus(201)->json('data');

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => (int) $location['id'],
            'product_id' => (int) $product['id'],
            'quantity_delta' => 25,
            'reason_code' => 'purchase',
        ])->assertStatus(201);

        app('auth')->forgetGuards();

        return ['product_id' => (int) $product['id'], 'slug' => (string) $company->slug];
    }

    /**
     * @return array{token: string, id: int}
     */
    private function buyer(string $email): array
    {
        $data = $this->postJson('/api/v1/public/market/account/register', [
            'name' => 'Awa Cliente',
            'email' => $email,
            'password' => self::PASSWORD,
        ])->assertStatus(201)->json('data');

        return ['token' => (string) $data['token'], 'id' => (int) $data['account']['id']];
    }

    /**
     * Commande du compte chez le vendeur, passée à LIVRÉE directement en
     * base (le pilotage logistique vendeur est couvert par
     * RetailOnlineOrderTest — ici seul l'état final importe).
     */
    private function deliveredOrder(array $fx, array $buyer, string $key): string
    {
        $order = $this->postJson('/api/v1/public/market/orders', [
            'seller' => $fx['slug'],
            'items' => [['product_id' => $fx['product_id'], 'quantity' => 1]],
            'customer' => ['name' => 'Awa Cliente', 'phone' => '+221770000001'],
            'delivery' => ['address' => '5 avenue Bourguiba', 'city' => 'Dakar'],
            'payment_method' => 'cash',
            'idempotency_key' => $key,
        ], ['Authorization' => 'Bearer '.$buyer['token']])->assertStatus(201)->json('data');

        RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', (string) $order['reference'])
            ->update([
                'fulfillment_status' => RetailFulfillmentStatus::Delivered->value,
                'delivered_at' => now(),
            ]);

        return (string) $order['reference'];
    }

    public function test_favorites_are_idempotent_bounded_to_the_account_and_fail_closed(): void
    {
        $fx = $this->publicProduct($this->principalA, $this->companyA, 'SKU-FAV-1', 'XOF');
        $buyer = $this->buyer('fav@example.test');
        $other = $this->buyer('fav-autre@example.test');
        $headers = ['Authorization' => 'Bearer '.$buyer['token']];

        // Cible inconnue / non publique → 404 fail-closed.
        $this->postJson('/api/v1/public/market/account/favorites', [
            'target_type' => 'product',
            'product_id' => 99_999,
        ], $headers)->assertStatus(404);

        $created = $this->postJson('/api/v1/public/market/account/favorites', [
            'target_type' => 'product',
            'product_id' => $fx['product_id'],
        ], $headers)->assertStatus(201)->json('data');

        // Idempotent : re-poster la même cible → 200, même favori.
        $this->postJson('/api/v1/public/market/account/favorites', [
            'target_type' => 'product',
            'product_id' => $fx['product_id'],
        ], $headers)->assertStatus(200)->assertJsonPath('data.id', (int) $created['id']);

        // Favori boutique.
        $this->postJson('/api/v1/public/market/account/favorites', [
            'target_type' => 'seller',
            'seller' => $fx['slug'],
        ], $headers)->assertStatus(201);

        $this->getJson('/api/v1/public/market/account/favorites', $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Liste d'un AUTRE compte : vide (bornage strict).
        $this->getJson('/api/v1/public/market/account/favorites', [
            'Authorization' => 'Bearer '.$other['token'],
        ])->assertStatus(200)->assertJsonCount(0, 'data');

        // Retrait borné au compte : l'autre acheteur → 404.
        $this->deleteJson('/api/v1/public/market/account/favorites/'.$created['id'], [], [
            'Authorization' => 'Bearer '.$other['token'],
        ])->assertStatus(404);

        $this->deleteJson('/api/v1/public/market/account/favorites/'.$created['id'], [], $headers)
            ->assertStatus(200);

        $this->getJson('/api/v1/public/market/account/favorites', $headers)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Sans token → 401.
        $this->getJson('/api/v1/public/market/account/favorites')->assertStatus(401);
    }

    public function test_review_requires_a_delivered_order_and_stays_private_until_approved(): void
    {
        $fx = $this->publicProduct($this->principalA, $this->companyA, 'SKU-REV-1', 'XOF');
        $buyer = $this->buyer('avis@example.test');
        $headers = ['Authorization' => 'Bearer '.$buyer['token']];

        // Aucune commande livrée → 422 REVIEW_NOT_ALLOWED.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'target_type' => 'product',
            'product_id' => $fx['product_id'],
            'rating' => 5,
        ], $headers)->assertStatus(422);

        $this->deliveredOrder($fx, $buyer, 'rev-7814-a');

        $review = $this->postJson('/api/v1/public/market/account/reviews', [
            'target_type' => 'product',
            'product_id' => $fx['product_id'],
            'rating' => 4,
            'comment' => 'Très bon produit, livraison rapide.',
        ], $headers)->assertStatus(201)->json('data');

        $this->assertSame(MarketReview::STATUS_PENDING, $review['status']);

        // Un avis par cible et par compte → 422.
        $this->postJson('/api/v1/public/market/account/reviews', [
            'target_type' => 'product',
            'product_id' => $fx['product_id'],
            'rating' => 5,
        ], $headers)->assertStatus(422);

        // PAS public tant que non approuvé (fail-closed).
        $this->getJson("/api/v1/public/market/products/{$fx['product_id']}/reviews")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('rating.count', 0);
    }

    public function test_moderation_is_rbac_and_tenant_bounded_and_publishes_approved_reviews(): void
    {
        $fx = $this->publicProduct($this->principalA, $this->companyA, 'SKU-REV-2', 'XOF');
        $buyer = $this->buyer('avis2@example.test');
        $headers = ['Authorization' => 'Bearer '.$buyer['token']];

        $this->deliveredOrder($fx, $buyer, 'rev-7814-b');

        $productReview = $this->postJson('/api/v1/public/market/account/reviews', [
            'target_type' => 'product',
            'product_id' => $fx['product_id'],
            'rating' => 5,
            'comment' => 'Excellent.',
        ], $headers)->assertStatus(201)->json('data');

        $sellerReview = $this->postJson('/api/v1/public/market/account/reviews', [
            'target_type' => 'seller',
            'seller' => $fx['slug'],
            'rating' => 4,
        ], $headers)->assertStatus(201)->json('data');

        // Le vendeur B ne voit ni ne modère les avis du vendeur A (404).
        Sanctum::actingAs($this->principalB);
        $this->getJson('/api/v1/retail/online/reviews')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/retail/online/reviews/{$productReview['id']}/approve")
            ->assertStatus(404);

        // Un employé sans rôle manager ne modère pas (403).
        Sanctum::actingAs($this->employeeA);
        $this->postJson("/api/v1/retail/online/reviews/{$productReview['id']}/approve")
            ->assertStatus(403);

        // Le principal A modère : approve produit, reject boutique.
        Sanctum::actingAs($this->principalA);
        $this->getJson('/api/v1/retail/online/reviews?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->postJson("/api/v1/retail/online/reviews/{$productReview['id']}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', MarketReview::STATUS_APPROVED);

        $this->postJson("/api/v1/retail/online/reviews/{$sellerReview['id']}/reject")
            ->assertStatus(200)
            ->assertJsonPath('data.status', MarketReview::STATUS_REJECTED);

        // Transition invalide (déjà modéré) → 422.
        $this->postJson("/api/v1/retail/online/reviews/{$productReview['id']}/reject")
            ->assertStatus(422);

        app('auth')->forgetGuards();

        // L'avis produit approuvé est PUBLIC (prénom seul, note moyenne).
        $this->getJson("/api/v1/public/market/products/{$fx['product_id']}/reviews")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.author', 'Awa')
            ->assertJsonPath('rating.count', 1);

        // L'avis boutique rejeté n'apparaît jamais.
        $this->getJson("/api/v1/public/market/sellers/{$fx['slug']}/reviews")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('rating.count', 0);
    }
}
