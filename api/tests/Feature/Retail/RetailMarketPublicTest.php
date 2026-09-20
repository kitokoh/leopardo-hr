<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7807) — Vitrine publique de la marketplace Leopardo
 * Marche : visibilite opt-in (produit ET boutique), isolation tenant,
 * filtres/tri de la recherche cross-tenant, DTO public strict (jamais de
 * company_id ni de quantites de stock), 404 fail-closed.
 */
class RetailMarketPublicTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

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

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyNoFlag = $companyNoFlag;

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
     * Active la boutique en ligne du tenant de l'acteur.
     *
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
    private function storeCategory(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/categories', array_merge([
            'name' => 'Boissons fraiches',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * Cree un produit PUBLIE (et, par defaut, visible en ligne).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePublishedProduct(Employee $actor, array $overrides = [], bool $onlineVisible = true): array
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

        if ($onlineVisible) {
            $product = $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")
                ->assertStatus(200)
                ->json('data');
        }

        return $product;
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

    public function test_product_hidden_until_product_and_shop_opt_in(): void
    {
        // Produit publie mais PAS visible en ligne, boutique non activee.
        $product = $this->storePublishedProduct($this->principalA, [], false);

        $this->getJson('/api/v1/public/market/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Visibilite produit seule : la boutique n'est pas activee → cache.
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")
            ->assertStatus(200)
            ->assertJsonPath('data.online_visible', true);

        $this->getJson('/api/v1/public/market/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Boutique activee → le produit apparait.
        $this->enableShop($this->principalA);

        $this->getJson('/api/v1/public/market/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jus de bissap 50cl');

        // Depublication en ligne → disparait immediatement.
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/products/{$product['id']}/unpublish-online")
            ->assertStatus(200)
            ->assertJsonPath('data.online_visible', false);

        $this->getJson('/api/v1/public/market/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_public_dto_never_leaks_internal_fields_and_exposes_availability(): void
    {
        $this->enableShop($this->principalA);
        $product = $this->storePublishedProduct($this->principalA, ['cost_minor' => 800]);
        $location = $this->storeLocation($this->principalA);

        // DTO strict : liste FERMEE de champs publics — jamais de
        // company_id, cost_minor, sku, quantites de stock ni meta.
        $response = $this->getJson('/api/v1/public/market/products')->assertStatus(200);

        $this->assertSame(
            ['id', 'name', 'description', 'price_minor', 'currency', 'image_url', 'category', 'seller', 'available', 'rating_avg', 'rating_count'],
            array_keys($response->json('data.0'))
        );
        $this->assertSame(
            ['name', 'slug', 'city'],
            array_keys($response->json('data.0.seller'))
        );

        // Sans stock : available = false (aucune quantite exposee).
        $response->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.seller.slug', $this->companyA->slug)
            ->assertJsonPath('data.0.seller.name', 'Boutique Test')
            ->assertJsonPath('data.0.price_minor', 1_500);

        // Avec stock : available = true.
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 5.0);

        $this->getJson('/api/v1/public/market/products')
            ->assertStatus(200)
            ->assertJsonPath('data.0.available', true);

        // Fiche produit publique : meme DTO strict.
        $detail = $this->getJson("/api/v1/public/market/products/{$product['id']}")
            ->assertStatus(200)
            ->assertJsonPath('data.available', true);

        $this->assertSame(
            ['id', 'name', 'description', 'price_minor', 'currency', 'image_url', 'category', 'seller', 'available', 'rating_avg', 'rating_count'],
            array_keys($detail->json('data'))
        );
    }

    public function test_search_filters_and_sort(): void
    {
        $this->enableShop($this->principalA);
        $this->enableShop($this->principalB, ['shop_name' => 'Souk Casablanca', 'city' => 'Casablanca', 'currency' => 'MAD']);

        $category = $this->storeCategory($this->principalA, ['name' => 'Boissons']);

        $this->storePublishedProduct($this->principalA, [
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-1',
            'price_minor' => 1_500,
            'category_id' => (int) $category['id'],
        ]);
        $this->storePublishedProduct($this->principalA, [
            'name' => 'The a la menthe',
            'sku' => 'SKU-2',
            'price_minor' => 900,
        ]);
        $this->storePublishedProduct($this->principalB, [
            'name' => 'Tajine artisanal',
            'sku' => 'SKU-3',
            'price_minor' => 12_000,
            'currency' => 'MAD',
        ]);

        // Recherche plein texte.
        $this->getJson('/api/v1/public/market/products?q=bissap')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jus de bissap 50cl');

        // Filtre vendeur (slug public).
        $this->getJson('/api/v1/public/market/products?seller='.$this->companyB->slug)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.seller.slug', $this->companyB->slug);

        // Slug vendeur inconnu : fail-closed, aucun resultat.
        $this->getJson('/api/v1/public/market/products?seller=unknown-shop')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Filtre categorie + DTO categorie {id, name}.
        $this->getJson('/api/v1/public/market/products?category='.$category['id'])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category.name', 'Boissons');

        // Bornes de prix en minor units.
        $this->getJson('/api/v1/public/market/products?min_price=1000&max_price=2000')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_minor', 1_500);

        // Tri par prix croissant / decroissant.
        $this->getJson('/api/v1/public/market/products?sort=price_asc')
            ->assertStatus(200)
            ->assertJsonPath('data.0.price_minor', 900);

        $this->getJson('/api/v1/public/market/products?sort=price_desc')
            ->assertStatus(200)
            ->assertJsonPath('data.0.price_minor', 12_000);

        // per_page borne a 50.
        $this->getJson('/api/v1/public/market/products?per_page=51')->assertStatus(422);
    }

    public function test_tenant_isolation_only_opt_in_sellers_are_visible(): void
    {
        // A opt-in, B publie des produits mais boutique NON activee.
        $this->enableShop($this->principalA);
        $this->storePublishedProduct($this->principalA);
        $this->storePublishedProduct($this->principalB, [
            'name' => 'Produit cache',
            'sku' => 'SKU-B1',
            'currency' => 'MAD',
        ]);

        $this->getJson('/api/v1/public/market/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jus de bissap 50cl');

        // Tenant sans feature flag retail : meme avec une ligne de reglages
        // forcee en base, il reste invisible (kill switch plateforme).
        RetailOnlineSettings::query()->create([
            'company_id' => (string) $this->companyNoFlag->id,
            'enabled' => true,
            'shop_name' => 'Boutique sans flag',
            'currency' => 'DZD',
            'version' => 1,
        ]);

        $this->getJson('/api/v1/public/market/sellers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $this->companyA->slug);
    }

    public function test_product_show_is_fail_closed(): void
    {
        $this->enableShop($this->principalA);

        // Produit publie mais non visible en ligne → 404.
        $hidden = $this->storePublishedProduct($this->principalA, ['sku' => 'SKU-H'], false);
        $this->getJson("/api/v1/public/market/products/{$hidden['id']}")->assertStatus(404);

        // Produit inconnu → 404.
        $this->getJson('/api/v1/public/market/products/999999')->assertStatus(404);
    }

    public function test_sellers_listing_and_public_shop_page(): void
    {
        $this->enableShop($this->principalA, ['shop_description' => 'Epicerie fine de Dakar']);

        $category = $this->storeCategory($this->principalA, ['name' => 'Boissons']);

        $this->storePublishedProduct($this->principalA, ['category_id' => (int) $category['id']]);
        $this->storePublishedProduct($this->principalA, ['name' => 'Cafe Touba', 'sku' => 'SKU-CAFE']);

        // Listing des boutiques activees : DTO ferme, sans company_id.
        $sellersResponse = $this->getJson('/api/v1/public/market/sellers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Boutique Test')
            ->assertJsonPath('data.0.slug', $this->companyA->slug)
            ->assertJsonPath('data.0.products_count', 2);

        $this->assertSame(
            ['name', 'slug', 'city', 'description', 'products_count', 'rating_avg', 'rating_count'],
            array_keys($sellersResponse->json('data.0'))
        );

        // Fiche publique : boutique + categories publiques.
        $this->getJson('/api/v1/public/market/sellers/'.$this->companyA->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.description', 'Epicerie fine de Dakar')
            ->assertJsonPath('data.products_count', 2)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.name', 'Boissons')
            ->assertJsonPath('data.categories.0.products_count', 1);

        // Slug inconnu → 404 fail-closed.
        $this->getJson('/api/v1/public/market/sellers/unknown-shop')->assertStatus(404);

        // Boutique non activee → 404 fail-closed (pas de probing).
        $this->getJson('/api/v1/public/market/sellers/'.$this->companyB->slug)->assertStatus(404);
    }
}
