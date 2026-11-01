<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Discovery\PublicDiscoveryService;
use App\Shared\Services\PublicCommerce\PlatformSettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * COULOIR 16 — M0 · Seuil de catégories réglable depuis le Superadmin.
 *
 * Le cahier des charges impose : « une catégorie n'apparaît que si la ville
 * compte au moins [5] Pages publiées dedans ; seuil réglable depuis le
 * Superadmin ». Au HEAD, aucun `platform_settings` n'existait : le seuil ne
 * pouvait être ni réglé ni lu. Ces tests prouvent que le réglage existe, qu'il
 * est lu, et qu'il est appliqué.
 *
 * Ces tests sont des GARDE-FOUS (comportement attendu), pas des constats :
 * ils ne portent donc PAS le groupe `audit` et restent dans la CI bloquante.
 */
class DiscoveryCategoryThresholdTest extends TestCase
{
    /**
     * @param  iterable<PublicDiscoverableProvider>  $providers
     */
    private function service(iterable $providers, ?PlatformSettingsRepository $settings = null): PublicDiscoveryService
    {
        return new PublicDiscoveryService(
            $providers,
            $this->createMock(GeoServiceContract::class),
            $settings,
        );
    }

    /**
     * Un provider factice : `search()` rend les items qu'on lui donne.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function provider(string $type, array $items): PublicDiscoverableProvider
    {
        $provider = $this->createMock(PublicDiscoverableProvider::class);
        $provider->method('type')->willReturn($type);
        $provider->method('supportedActions')->willReturn(['contact']);
        $provider->method('supportedActionUrls')->willReturn(['contact' => null]);
        $provider->method('search')->willReturn($items);

        return $provider;
    }

    /**
     * @param  list<string>  $categories
     * @return list<array<string, mixed>>
     */
    private function items(array $categories): array
    {
        $items = [];

        foreach ($categories as $index => $category) {
            $items[] = [
                'type' => 'restaurant',
                'slug' => 'slug-'.$index,
                'name' => 'Commerce '.$index,
                'category' => $category,
                'city' => 'Paris',
                'country' => 'FR',
                'description' => null,
                'image_url' => null,
                'latitude' => null,
                'longitude' => null,
                'url' => '/restaurants/slug-'.$index,
            ];
        }

        return $items;
    }

    /**
     * Sans réglage en base, le défaut du cahier des charges (5) s'applique.
     */
    public function test_default_threshold_is_five_when_no_setting_exists(): void
    {
        $this->assertSame(5, PlatformSettingsRepository::DEFAULT_CATEGORY_MIN_PAGES);

        // Isolation : un test précédent a pu insérer la clé dans la vraie
        // table (les tests ne sont pas transactionnels ici). Sans ce nettoyage,
        // ce test lirait le seuil laissé par `test_threshold_is_read_from_...`
        // et échouerait selon l'ORDRE d'exécution — un faux négatif.
        if (Schema::hasTable('public.platform_settings')) {
            DB::table('public.platform_settings')
                ->where('key', PlatformSettingsRepository::DISCOVERY_CATEGORY_MIN_PAGES)
                ->delete();
        }

        $repository = new PlatformSettingsRepository;

        $this->assertSame(
            5,
            $repository->categoryMinPages('Paris'),
            'Sans réglage, le seuil doit être 5 — jamais 0, qui afficherait tout.',
        );
    }

    /**
     * Une catégorie sous le seuil est renvoyée `visible: false`, pas supprimée.
     *
     * Supprimer la ligne serait indistinguable d'une absence de données : le
     * frontend ne pourrait pas dire « bientôt disponible ».
     */
    public function test_categories_below_the_threshold_are_hidden_but_still_listed(): void
    {
        // 6 restaurants (au-dessus du seuil 5), 2 pharmacies (en dessous).
        $provider = $this->provider('restaurant', $this->items([
            'restaurant', 'restaurant', 'restaurant',
            'restaurant', 'restaurant', 'restaurant',
            'pharmacie', 'pharmacie',
        ]));

        $result = $this->service([$provider])->categories('Paris');

        $this->assertSame(5, $result['meta']['threshold']);
        $this->assertSame('Paris', $result['meta']['city']);

        $byKey = [];

        foreach ($result['data'] as $row) {
            $byKey[$row['key']] = $row;
        }

        $this->assertArrayHasKey('restaurant', $byKey);
        $this->assertArrayHasKey('pharmacie', $byKey, 'Une catégorie sous le seuil doit rester listée.');

        $this->assertTrue($byKey['restaurant']['visible']);
        $this->assertSame(6, $byKey['restaurant']['count']);

        $this->assertFalse($byKey['pharmacie']['visible'], 'Deux Pages < seuil 5 : invisible.');
        $this->assertSame(2, $byKey['pharmacie']['count']);
    }

    /**
     * Le seuil vient de la BASE, pas d'une constante : le changer change le
     * résultat sans redéploiement.
     */
    public function test_threshold_is_read_from_platform_settings(): void
    {
        // La fixture MVP ne charge pas `platform_settings` : on la crée ici
        // pour que ce test porte sur une VRAIE lecture en base, pas sur un
        // mock. Un test ignoré ne prouve rien.
        // `search_path` vaut `shared_tenants,public` : une table créée sans
        // qualification atterrit dans le schéma TENANT, et la lecture du dépôt
        // (qui interroge `public.platform_settings`) ne la voit pas. Le test
        // passait alors au vert pour la MAUVAISE raison — le dépôt renvoyait son
        // défaut 5, et l'assertion « 2 » échouait en accusant le code applicatif.
        if (! Schema::hasTable('public.platform_settings')) {
            Schema::create('public.platform_settings', function ($table): void {
                $table->string('key', 120)->primary();
                $table->jsonb('value');
                $table->string('description', 255)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestampsTz();
            });
        }

        DB::table('public.platform_settings')->updateOrInsert(
            ['key' => PlatformSettingsRepository::DISCOVERY_CATEGORY_MIN_PAGES],
            ['value' => json_encode(['default' => 2]), 'updated_at' => now(), 'created_at' => now()],
        );

        $repository = new PlatformSettingsRepository;

        $this->assertSame(2, $repository->categoryMinPages('Paris'));

        $provider = $this->provider('restaurant', $this->items(['pharmacie', 'pharmacie']));

        $result = $this->service([$provider], $repository)->categories('Paris');

        $this->assertSame(2, $result['meta']['threshold']);
        $this->assertTrue(
            $result['data'][0]['visible'],
            'Avec un seuil réglé à 2, deux Pages suffisent : le réglage est bien appliqué.',
        );
    }

    /**
     * Un seuil par ville prime sur le défaut global.
     */
    public function test_per_city_threshold_overrides_the_default(): void
    {
        $repository = new class extends PlatformSettingsRepository
        {
            public function raw(string $key): mixed
            {
                return ['default' => 5, 'by_city' => ['Paris' => 8]];
            }
        };

        $this->assertSame(8, $repository->categoryMinPages('Paris'));
        $this->assertSame(8, $repository->categoryMinPages('paris'), 'Comparaison insensible à la casse.');
        $this->assertSame(5, $repository->categoryMinPages('Lyon'), 'Ville absente → défaut global.');
        $this->assertSame(5, $repository->categoryMinPages(null));
    }

    /**
     * Un seuil illisible ou absurde ne casse jamais la découverte publique.
     */
    public function test_invalid_threshold_falls_back_to_the_default(): void
    {
        $zero = new class extends PlatformSettingsRepository
        {
            public function raw(string $key): mixed
            {
                return 0;
            }
        };

        $this->assertSame(
            5,
            $zero->categoryMinPages('Paris'),
            'Un seuil à 0 afficherait TOUT : il doit retomber sur le défaut.',
        );

        $garbage = new class extends PlatformSettingsRepository
        {
            public function raw(string $key): mixed
            {
                return 'not-a-number';
            }
        };

        $this->assertSame(5, $garbage->categoryMinPages('Paris'));
    }

    /**
     * La route est PUBLIQUE et isolée : aucun auth, aucun middleware tenant.
     */
    public function test_categories_route_is_public_and_tenant_free(): void
    {
        $route = app('router')->getRoutes()->match(
            app('request')->create('/api/v1/public/discovery/categories?city=Paris', 'GET')
        );

        $middleware = $route->gatherMiddleware();

        $this->assertNotContains('auth:sanctum', $middleware);
        $this->assertNotContains('auth:user_api', $middleware);
        $this->assertNotContains('tenant', $middleware, 'La découverte publique ne doit jamais ouvrir un contexte tenant.');
        $this->assertContains('throttle:shop-public', $middleware);
    }
}
