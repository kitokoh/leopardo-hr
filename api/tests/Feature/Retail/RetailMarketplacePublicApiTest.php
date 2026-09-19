<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7807) — API publique marketplace : découverte cross-tenant
 * des produits et boutiques opt-in, sans auth (`/public/market/*`).
 *
 * Couvre : visibilité opt-in (boutique enabled + produit published +
 * online_visible + feature retail), isolation tenant (le filtre seller ne
 * fuit jamais un autre tenant), filtres (q, seller, category, min/max_price,
 * sort, per_page ≤ 50), DTO public fail-closed (aucune donnée interne),
 * annuaire des boutiques et 404 fail-closed sur les fiches.
 */
class RetailMarketplacePublicApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyOff;

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

        // Tenant SANS opt-in boutique (mais flag retail actif) : jamais visible.
        /** @var Company $companyOff */
        $companyOff = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyOff->setFeature('retail', true);
        $companyOff->save();
        $this->companyOff = $companyOff;

        RetailOnlineSettings::query()->create([
            'company_id' => (string) $this->companyA->id,
            'slug' => 'boutique-dakar',
            'display_name' => 'Boutique Dakar',
            'description' => 'Épicerie fine du plateau.',
            'enabled' => true,
        ]);

        RetailOnlineSettings::query()->create([
            'company_id' => (string) $this->companyB->id,
            'slug' => 'souk-casa',
            'display_name' => 'Souk Casa',
            'enabled' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(Company $company, array $overrides = []): RetailProduct
    {
        static $sequence = 0;
        $sequence++;

        /** @var RetailProduct $product */
        $product = RetailProduct::query()->create(array_merge([
            'company_id' => (string) $company->id,
            'name' => 'Produit '.$sequence,
            'slug' => 'produit-'.$sequence,
            'sku' => 'SKU-'.$sequence,
            'price_minor' => 1_000,
            'currency' => (string) $company->currency,
            'status' => 'published',
            'online_visible' => true,
        ], $overrides));

        return $product;
    }

    public function test_only_opted_in_visible_products_are_listed(): void
    {
        $visible = $this->product($this->companyA, ['name' => 'Jus de bissap']);
        $this->product($this->companyA, ['name' => 'Brouillon', 'status' => 'draft']);
        $this->product($this->companyA, ['name' => 'Hors ligne', 'online_visible' => false]);
        $this->product($this->companyOff, ['name' => 'Boutique fermée']);

        $response = $this->getJson('/api/v1/public/market/products')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['Jus de bissap'], $names);
        $this->assertSame($visible->id, $response->json('data.0.id'));
    }

    public function test_disabled_shop_and_missing_feature_flag_hide_products(): void
    {
        $this->product($this->companyB, ['name' => 'Tajine']);

        // Boutique désactivée : plus rien.
        RetailOnlineSettings::query()
            ->where('company_id', (string) $this->companyB->id)
            ->update(['enabled' => false]);

        $this->getJson('/api/v1/public/market/products')->assertOk()->assertJsonCount(0, 'data');

        // Boutique réactivée mais kill switch plateforme (feature retail) coupé.
        RetailOnlineSettings::query()
            ->where('company_id', (string) $this->companyB->id)
            ->update(['enabled' => true]);
        $this->companyB->setFeature('retail', false);
        $this->companyB->save();

        $this->getJson('/api/v1/public/market/products')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_public_dto_never_exposes_internal_data(): void
    {
        /** @var RetailCategory $category */
        $category = RetailCategory::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Boissons',
            'slug' => 'boissons',
        ]);

        $product = $this->product($this->companyA, [
            'name' => 'Jus de bissap',
            'category_id' => $category->id,
            'cost_minor' => 700,
            'image_url' => 'https://cdn.example.com/bissap.jpg',
        ]);

        RetailStockLevel::query()->create([
            'company_id' => (string) $this->companyA->id,
            'location_id' => 1,
            'product_id' => $product->id,
            'quantity' => '12.000',
        ]);

        $data = $this->getJson('/api/v1/public/market/products/'.$product->id)
            ->assertOk()
            ->json('data');

        $this->assertSame([
            'id', 'name', 'description', 'price_minor', 'currency',
            'image_url', 'category', 'seller', 'available',
        ], array_keys($data));

        $this->assertSame('Boissons', $data['category']);
        $this->assertSame(['slug' => 'boutique-dakar', 'display_name' => 'Boutique Dakar'], $data['seller']);
        $this->assertTrue($data['available']);

        // Jamais de stock chiffré, de marge ni d'identifiant tenant.
        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('company_id', $payload);
        $this->assertStringNotContainsString('cost_minor', $payload);
        $this->assertStringNotContainsString('12.000', $payload);
        $this->assertStringNotContainsString((string) $this->companyA->id, $payload);
    }

    public function test_availability_is_a_boolean_derived_from_stock(): void
    {
        $outOfStock = $this->product($this->companyA, ['name' => 'Épuisé']);

        RetailStockLevel::query()->create([
            'company_id' => (string) $this->companyA->id,
            'location_id' => 1,
            'product_id' => $outOfStock->id,
            'quantity' => '0.000',
        ]);

        $untracked = $this->product($this->companyA, ['name' => 'Non suivi']);

        $this->assertFalse($this->getJson('/api/v1/public/market/products/'.$outOfStock->id)->json('data.available'));
        $this->assertTrue($this->getJson('/api/v1/public/market/products/'.$untracked->id)->json('data.available'));
    }

    public function test_filters_and_sort_and_per_page_cap(): void
    {
        $this->product($this->companyA, ['name' => 'Jus de bissap', 'price_minor' => 1_500]);
        $this->product($this->companyA, ['name' => 'Jus de gingembre', 'price_minor' => 2_500]);
        $this->product($this->companyB, ['name' => 'Jus d\'orange', 'price_minor' => 2_000]);
        $this->product($this->companyB, ['name' => 'Thé à la menthe', 'price_minor' => 800]);

        // q + tri par prix croissant.
        $names = collect($this->getJson('/api/v1/public/market/products?q=jus&sort=price_asc')
            ->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Jus de bissap', 'Jus d\'orange', 'Jus de gingembre'], $names);

        // Filtre seller : borné au tenant de la boutique (isolation).
        $names = collect($this->getJson('/api/v1/public/market/products?seller=souk-casa')
            ->assertOk()->json('data'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['Jus d\'orange', 'Thé à la menthe'], $names);

        // Fourchette de prix en minor units.
        $names = collect($this->getJson('/api/v1/public/market/products?min_price=1000&max_price=2000')
            ->assertOk()->json('data'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['Jus d\'orange', 'Jus de bissap'], $names);

        // per_page plafonné à 50.
        $this->getJson('/api/v1/public/market/products?per_page=51')->assertStatus(422);
        $this->assertSame(1, $this->getJson('/api/v1/public/market/products?per_page=1')
            ->assertOk()->json('meta.per_page'));
    }

    public function test_category_filter_matches_by_name(): void
    {
        /** @var RetailCategory $category */
        $category = RetailCategory::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Boissons',
            'slug' => 'boissons',
        ]);

        $this->product($this->companyA, ['name' => 'Jus de bissap', 'category_id' => $category->id]);
        $this->product($this->companyA, ['name' => 'Savon noir']);

        $names = collect($this->getJson('/api/v1/public/market/products?category=boissons')
            ->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Jus de bissap'], $names);
    }

    public function test_product_detail_is_fail_closed_404(): void
    {
        $draft = $this->product($this->companyA, ['status' => 'draft']);
        $hidden = $this->product($this->companyA, ['online_visible' => false]);
        $closedShop = $this->product($this->companyOff);

        $this->getJson('/api/v1/public/market/products/'.$draft->id)->assertNotFound();
        $this->getJson('/api/v1/public/market/products/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/v1/public/market/products/'.$closedShop->id)->assertNotFound();
        $this->getJson('/api/v1/public/market/products/999999')->assertNotFound();
        $this->getJson('/api/v1/public/market/products/abc')->assertNotFound();
    }

    public function test_sellers_directory_lists_only_enabled_shops(): void
    {
        $this->product($this->companyA, ['name' => 'Jus de bissap']);

        RetailOnlineSettings::query()->create([
            'company_id' => (string) $this->companyOff->id,
            'slug' => 'boutique-fermee',
            'display_name' => 'Boutique fermée',
            'enabled' => false,
        ]);

        $data = $this->getJson('/api/v1/public/market/sellers')->assertOk()->json('data');

        $this->assertSame(['boutique-dakar', 'souk-casa'], collect($data)->pluck('slug')->all());
        $this->assertSame(1, collect($data)->firstWhere('slug', 'boutique-dakar')['product_count']);
        $this->assertStringNotContainsString('company_id', json_encode($data, JSON_THROW_ON_ERROR));

        $this->getJson('/api/v1/public/market/sellers/boutique-dakar')->assertOk()
            ->assertJsonPath('data.display_name', 'Boutique Dakar');
        $this->getJson('/api/v1/public/market/sellers/boutique-fermee')->assertNotFound();
        $this->getJson('/api/v1/public/market/sellers/inconnue')->assertNotFound();
    }

    public function test_public_routes_require_no_authentication(): void
    {
        $this->getJson('/api/v1/public/market/products')->assertOk();
        $this->getJson('/api/v1/public/market/sellers')->assertOk();
    }
}
