<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-901 (#7746) — Annuaire PUBLIC des restaurants (sans auth).
 *
 * Couvre : filtre `is_public` (une branche privée ou d'un tenant sans flag
 * n'apparaît jamais), DTO public strict (aucun `company_id`/`id` interne),
 * recherche par proximité haversine (`near` + `radius_km`, tri par
 * distance), filtres ville/type, profil public par slug (horaires + menu
 * publié uniquement) et 404 fail-closed (slug inconnu ou dépublié).
 */
class RestaurantPublicDirectoryTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePublicBranch(array $attributes = [], bool $featureEnabled = true): RestaurantBranch
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', $featureEnabled);
        $company->save();

        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create(array_merge([
                'is_public' => true,
                'status' => 'active',
            ], $attributes))
        );
    }

    public function test_directory_lists_only_public_branches_with_strict_public_dto(): void
    {
        $this->makePublicBranch([
            'name' => 'Le Public',
            'public_slug' => 'le-public',
            'establishment_type' => 'restaurant',
            'city' => 'Douala',
        ]);
        $this->makePublicBranch([
            'name' => 'Le Prive',
            'public_slug' => null,
            'is_public' => false,
        ]);
        // Tenant sans flag restaurantmanager → jamais listé (fail-closed).
        $this->makePublicBranch([
            'name' => 'Sans Flag',
            'public_slug' => 'sans-flag',
        ], featureEnabled: false);

        $response = $this->getJson('/api/v1/public/restaurants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'le-public')
            ->assertJsonPath('data.0.name', 'Le Public')
            ->assertJsonPath('data.0.establishment_type', 'restaurant')
            ->assertJsonPath('data.0.city', 'Douala');

        // DTO public STRICT : aucune donnée interne.
        $item = $response->json('data.0');
        $this->assertIsArray($item);
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('company_id', $item);
        $this->assertArrayNotHasKey('code', $item);
        $this->assertArrayNotHasKey('status', $item);
    }

    public function test_directory_filters_by_city_type_and_search(): void
    {
        $this->makePublicBranch([
            'name' => 'Pizzeria Napoli',
            'public_slug' => 'pizzeria-napoli',
            'establishment_type' => 'pizzeria',
            'cuisine_types' => ['italienne'],
            'city' => 'Douala',
        ]);
        $this->makePublicBranch([
            'name' => 'Cafe Central',
            'public_slug' => 'cafe-central',
            'establishment_type' => 'cafe',
            'cuisine_types' => ['patisserie'],
            'city' => 'Yaounde',
        ]);

        $this->getJson('/api/v1/public/restaurants?city=douala')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'pizzeria-napoli');

        $this->getJson('/api/v1/public/restaurants?type=cafe')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cafe-central');

        $this->getJson('/api/v1/public/restaurants?cuisine=italienne')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'pizzeria-napoli');

        $this->getJson('/api/v1/public/restaurants?q=napoli')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'pizzeria-napoli');

        $this->getJson('/api/v1/public/restaurants?type=inconnu')
            ->assertStatus(422);
    }

    public function test_directory_near_sorts_by_haversine_distance_within_radius(): void
    {
        // Point de recherche : centre de Douala (4.0511, 9.7679).
        $this->makePublicBranch([
            'name' => 'Tout Pres',
            'public_slug' => 'tout-pres',
            'latitude' => 4.0520,
            'longitude' => 9.7700,
        ]);
        $this->makePublicBranch([
            'name' => 'Peripherie',
            'public_slug' => 'peripherie',
            'latitude' => 4.1000,
            'longitude' => 9.8100,
        ]);
        // Yaoundé, ~210 km → hors rayon (défaut 10 km, max 50 km).
        $this->makePublicBranch([
            'name' => 'Trop Loin',
            'public_slug' => 'trop-loin',
            'latitude' => 3.8480,
            'longitude' => 11.5021,
        ]);
        // Sans géoloc → exclue de la recherche par proximité.
        $this->makePublicBranch([
            'name' => 'Sans Geoloc',
            'public_slug' => 'sans-geoloc',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'tout-pres')
            ->assertJsonPath('data.1.slug', 'peripherie');

        $first = $response->json('data.0.distance_km');
        $second = $response->json('data.1.distance_km');
        $this->assertIsNumeric($first);
        $this->assertIsNumeric($second);
        $this->assertLessThan(1.0, (float) $first);
        $this->assertLessThan(10.0, (float) $second); // borne rayon défaut
        $this->assertGreaterThan((float) $first, (float) $second);

        // Rayon élargi (borné à 50 km) : Yaoundé reste hors de portée.
        $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679&radius_km=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Rayon > 50 refusé (validation stricte).
        $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679&radius_km=51')
            ->assertStatus(422);
    }

    public function test_show_returns_profile_hours_and_published_menu_only(): void
    {
        $branch = $this->makePublicBranch([
            'name' => 'Chez Awa',
            'public_slug' => 'chez-awa',
            'establishment_type' => 'restaurant',
            'cuisine_types' => ['africaine'],
            'city' => 'Douala',
            'public_description' => 'Cuisine locale.',
            'latitude' => 4.0511,
            'longitude' => 9.7679,
        ]);

        /** @var Company $company */
        $company = Company::query()->findOrFail($branch->company_id);

        app(TenantManager::class)->withinTenant($company, function () use ($branch): void {
            RestaurantHour::query()->create([
                'branch_id' => $branch->id,
                'day_of_week' => 0,
                'opens_at' => '09:00',
                'closes_at' => '22:00',
                'is_closed' => false,
            ]);

            $category = RestaurantCategory::factory()->create(['name' => 'Plats', 'sort_order' => 1]);

            RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'category_id' => $category->id,
                'name' => 'Poulet DG',
                'price_minor' => 4500,
                'currency' => 'XAF',
                'is_available' => true,
                'is_published_online' => true,
            ]);
            RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'category_id' => $category->id,
                'name' => 'Non Publie',
                'is_available' => true,
                'is_published_online' => false,
            ]);
            RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'category_id' => $category->id,
                'name' => 'Indisponible',
                'is_available' => false,
                'is_published_online' => true,
            ]);
        });

        $response = $this->getJson('/api/v1/public/restaurants/chez-awa')
            ->assertOk()
            ->assertJsonPath('data.slug', 'chez-awa')
            ->assertJsonPath('data.name', 'Chez Awa')
            ->assertJsonPath('data.establishment_type', 'restaurant')
            ->assertJsonPath('data.city', 'Douala')
            ->assertJsonPath('data.hours.0.day_of_week', 0)
            ->assertJsonPath('data.hours.0.is_closed', false)
            ->assertJsonPath('data.menu.0.name', 'Plats')
            ->assertJsonPath('data.menu.0.products.0.name', 'Poulet DG')
            ->assertJsonPath('data.menu.0.products.0.price_minor', 4500)
            ->assertJsonPath('data.menu.0.products.0.currency', 'XAF');

        // Seul le produit publié ET disponible figure au menu public.
        $products = $response->json('data.menu.0.products');
        $this->assertIsArray($products);
        $names = collect($products)->pluck('name')->all();
        $this->assertSame(['Poulet DG'], $names);

        // Aucune donnée interne dans le profil public.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('company_id', $data);
    }

    public function test_show_unknown_or_unpublished_slug_returns_404_fail_closed(): void
    {
        // Slug inconnu.
        $this->getJson('/api/v1/public/restaurants/slug-inconnu')->assertStatus(404);

        // Branche existante mais NON publiée (slug posé, is_public=false).
        $this->makePublicBranch([
            'is_public' => false,
            'public_slug' => 'jamais-visible',
        ]);
        $this->getJson('/api/v1/public/restaurants/jamais-visible')->assertStatus(404);
    }
}
