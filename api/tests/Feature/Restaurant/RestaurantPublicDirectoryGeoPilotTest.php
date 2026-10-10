<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GEO-06 (#8355, BC-33 GEO) — pilote : annuaire public Restaurant migré sur
 * le core géospatial (config `geo.pilots.restaurant_directory`).
 *
 * Comportement IDENTIQUE au repli legacy HAVERSINE_SQL (tests
 * RestaurantPublicDirectoryTest conservés verts) : rayon défaut 10 km,
 * max 50, tri par distance croissante, `distance_km` exposé, branches sans
 * géoloc exclues — et scope « annuaire public » strict (une branche privée
 * n'apparaît jamais, même la plus proche).
 */
class RestaurantPublicDirectoryGeoPilotTest extends TestCase
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

    public function test_pilot_on_sorts_by_geo_core_distance_within_radius(): void
    {
        config()->set('geo.pilots.restaurant_directory', true);

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
        // Yaoundé, ~210 km → hors rayon (défaut 10 km).
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
        $this->assertLessThan(10.0, (float) $second);
        $this->assertGreaterThan((float) $first, (float) $second);

        // Rayon élargi (borné à 50 km) : Yaoundé reste hors de portée.
        $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679&radius_km=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Rayon > 50 refusé (validation stricte — même contrat que legacy).
        $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679&radius_km=51')
            ->assertStatus(422);
    }

    public function test_pilot_on_never_exposes_non_public_branches(): void
    {
        config()->set('geo.pilots.restaurant_directory', true);

        // Branche PRIVÉE la plus proche : le scope « annuaire public » de la
        // vue d'adaptation doit l'exclure des candidats du core geo.
        $this->makePublicBranch([
            'name' => 'Privee Toute Proche',
            'public_slug' => null,
            'is_public' => false,
            'latitude' => 4.0512,
            'longitude' => 9.7680,
        ]);
        $this->makePublicBranch([
            'name' => 'Publique Proche',
            'public_slug' => 'publique-proche',
            'latitude' => 4.0520,
            'longitude' => 9.7700,
        ]);
        // Tenant sans flag restaurantmanager : exclu de l'annuaire.
        $this->makePublicBranch([
            'name' => 'Sans Flag',
            'public_slug' => 'sans-flag',
            'latitude' => 4.0512,
            'longitude' => 9.7680,
        ], featureEnabled: false);

        $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'publique-proche');
    }

    public function test_pilot_off_keeps_legacy_haversine_behavior(): void
    {
        // Parité de comportement du repli legacy (pilote inactif) — les deux
        // modes exposent le même contrat (tri, borne, distance_km).
        config()->set('geo.pilots.restaurant_directory', false);

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

        $this->getJson('/api/v1/public/restaurants?near=4.0511,9.7679')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'tout-pres')
            ->assertJsonPath('data.1.slug', 'peripherie');
    }
}
