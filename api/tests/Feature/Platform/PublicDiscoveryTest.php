<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * SUPERAPP-P0 (#8611) — Découverte publique unifiée cross-verticale.
 *
 * `GET /api/v1/public/discover` : surface NEUTRE qui agrège les entités
 * publiques de PLUSIEURS verticales (restaurants, vitrines) via les providers
 * `public.discoverable`, sans auth ni middleware tenant.
 *
 * Couvre : multi-verticale en une réponse, fail-closed (is_public=false,
 * société suspendue, feature verticale absente), 422 sur `?type=` inconnu,
 * tri par distance + exclusion hors rayon, DTO public strict (aucun `id`
 * interne / `company_id`), borne de pagination.
 */
class PublicDiscoveryTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicRestaurant(array $attributes = [], bool $featureEnabled = true): RestaurantBranch
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

    private function publishedShowcase(string $slug, bool $featureEnabled = true): CompanyShowcase
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
            'name' => 'Vitrine '.$slug,
        ]);
        $company->setFeature('showcase', $featureEnabled);
        $company->save();

        return app(TenantManager::class)->withinTenant($company, function () use ($company): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Published,
                'theme' => 'default',
                'settings' => [],
            ]);

            return $showcase;
        });
    }

    public function test_discover_aggregates_multiple_verticals_in_one_response(): void
    {
        $this->publicRestaurant([
            'name' => 'Chez Leopardo',
            'public_slug' => 'chez-leopardo',
            'establishment_type' => 'restaurant',
            'city' => 'Douala',
        ]);
        $this->publishedShowcase('boutique-alpha');

        $response = $this->getJson('/api/v1/public/discover')
            ->assertOk();

        $types = array_column($response->json('data'), 'type');
        $this->assertContains('restaurant', $types, 'la verticale restaurant doit être agrégée');
        $this->assertContains('showcase', $types, 'la verticale vitrine doit être agrégée');
        $this->assertGreaterThanOrEqual(2, count($types), 'au moins deux verticales en une réponse');
    }

    public function test_discover_is_fail_closed_for_private_suspended_and_flagless_entities(): void
    {
        // Public légitime.
        $this->publicRestaurant([
            'name' => 'Le Public',
            'public_slug' => 'le-public',
        ]);

        // is_public=false → jamais exposé.
        $this->publicRestaurant([
            'name' => 'Le Prive',
            'public_slug' => 'le-prive',
            'is_public' => false,
        ]);

        // Feature verticale absente → jamais exposé.
        $this->publicRestaurant([
            'name' => 'Sans Flag',
            'public_slug' => 'sans-flag',
        ], featureEnabled: false);

        // Société suspendue → jamais exposée.
        /** @var Company $suspended */
        $suspended = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF', 'status' => 'suspended']);
        $suspended->setFeature('restaurantmanager', true);
        $suspended->save();
        app(TenantManager::class)->withinTenant($suspended, fn () => RestaurantBranch::factory()->create([
            'is_public' => true,
            'status' => 'active',
            'public_slug' => 'suspendu',
            'name' => 'Suspendu',
        ]));

        $response = $this->getJson('/api/v1/public/discover')->assertOk();

        $slugs = array_column($response->json('data'), 'slug');
        $this->assertContains('le-public', $slugs);
        $this->assertNotContains('le-prive', $slugs, 'is_public=false ne doit jamais apparaître');
        $this->assertNotContains('sans-flag', $slugs, 'feature verticale absente ne doit jamais apparaître');
        $this->assertNotContains('suspendu', $slugs, 'société suspendue ne doit jamais apparaître');
    }

    public function test_discover_rejects_unknown_type_with_422(): void
    {
        $this->publicRestaurant(['name' => 'Le Public', 'public_slug' => 'le-public']);

        $this->getJson('/api/v1/public/discover?type=inconnu')
            ->assertStatus(422);
    }

    public function test_discover_filters_by_known_type(): void
    {
        $this->publicRestaurant(['name' => 'Le Public', 'public_slug' => 'le-public']);
        $this->publishedShowcase('boutique-alpha');

        $response = $this->getJson('/api/v1/public/discover?type=restaurant')->assertOk();

        $types = array_unique(array_column($response->json('data'), 'type'));
        $this->assertSame(['restaurant'], array_values($types));
    }

    public function test_discover_sorts_by_distance_and_excludes_out_of_radius(): void
    {
        // Douala ≈ 4.05, 9.70 — proche.
        $this->publicRestaurant([
            'name' => 'Proche',
            'public_slug' => 'proche',
            'latitude' => 4.06,
            'longitude' => 9.71,
        ]);
        // Yaoundé ≈ 3.85, 11.50 — ~200 km, hors rayon 5 km.
        $this->publicRestaurant([
            'name' => 'Loin',
            'public_slug' => 'loin',
            'latitude' => 3.85,
            'longitude' => 11.50,
        ]);

        $response = $this->getJson('/api/v1/public/discover?near=4.05,9.70&radius_km=5')->assertOk();

        $slugs = array_column($response->json('data'), 'slug');
        $this->assertContains('proche', $slugs);
        $this->assertNotContains('loin', $slugs, 'hors rayon → exclu');

        // Tri par distance croissante.
        $distances = array_column($response->json('data'), 'distance_km');
        $sorted = $distances;
        sort($sorted);
        $this->assertSame($sorted, $distances, 'les items doivent être triés par distance croissante');
    }

    public function test_discover_public_dto_contains_no_internal_data(): void
    {
        $this->publicRestaurant([
            'name' => 'Le Public',
            'public_slug' => 'le-public',
            'city' => 'Douala',
        ]);

        $response = $this->getJson('/api/v1/public/discover')->assertOk();
        $item = $response->json('data.0');

        $this->assertIsArray($item);
        foreach (['id', 'company_id', 'status', 'code', 'meta', 'stock', 'margin'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item, "le DTO public ne doit pas exposer `{$forbidden}`");
        }
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('slug', $item);
        $this->assertArrayHasKey('name', $item);
    }

    public function test_discover_bounds_per_page(): void
    {
        $this->publicRestaurant(['name' => 'Le Public', 'public_slug' => 'le-public']);

        // per_page > 50 → 422 (validation).
        $this->getJson('/api/v1/public/discover?per_page=999')
            ->assertStatus(422);

        // per_page valide → borné.
        $this->getJson('/api/v1/public/discover?per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    /**
     * #8761 — les capacités par type sont la source de vérité UNIQUE du
     * frontend : plus aucune liste codée en dur côté client. Un type qui ne
     * prend pas une action en charge la déclare absente, ce qui produit un
     * état « indisponible » explicite au lieu d'un bouton inopérant.
     */
    public function test_discover_publishes_real_per_type_actions(): void
    {
        $this->publicRestaurant(['name' => 'Le Public', 'public_slug' => 'le-public']);

        $response = $this->getJson('/api/v1/public/discover')->assertOk();

        $actions = $response->json('meta.actions');

        $this->assertIsArray($actions);

        // Le restaurant sert commande ET accepte le contact. Il ne declare PAS
        // `booking` : aucun contrat public de reservation n'existe (#8795 a
        // retire l'action fantome de `supportedActions()`). Cette assertion
        // etait restee sur l'ancienne valeur apres #8795 — elle exigeait une
        // capacite que le produit a deliberement retiree, donc `main` etait
        // rouge sur un test qui contredisait le code.
        $this->assertContains('order', $actions['restaurant']);
        $this->assertContains('contact', $actions['restaurant']);
        $this->assertNotContains(
            'booking',
            $actions['restaurant'],
            'P3-01 n\'est pas livre : Restaurant ne doit pas annoncer une reservation sans contrat public'
        );

        // La vitrine n'expose que le contact : ni commande ni réservation.
        $this->assertSame(['contact'], $actions['showcase']);
        $this->assertNotContains('booking', $actions['showcase']);
        $this->assertNotContains('order', $actions['showcase']);
    }

    /**
     * Fail-closed : un type présent dans `meta.types` mais sans action
     * déclarée ne doit jamais laisser le frontend supposer une capacité.
     */
    public function test_discover_actions_map_covers_every_discoverable_type(): void
    {
        $response = $this->getJson('/api/v1/public/discover')->assertOk();

        $types = $response->json('meta.types');
        $actions = $response->json('meta.actions');

        foreach ($types as $type) {
            $this->assertArrayHasKey($type, $actions, "le type `{$type}` doit déclarer ses actions");
            $this->assertIsArray($actions[$type]);
        }
    }
}
