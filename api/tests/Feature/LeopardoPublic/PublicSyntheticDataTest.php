<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Services\PublicCommerce\PlatformSettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M0 · Générateur de données synthétiques de la surface publique.
 *
 * Ces tests portent sur la COMMANDE, pas sur la découverte : ils prouvent que
 * le jeu de données est reproductible, isolé par ville, et nettoyable. La
 * découverte elle-même est couverte par `DiscoveryCategoryThresholdTest` et
 * `PublicParcoursEndToEndTest`.
 *
 * ⚠️ Aucun de ces tests n'est déclaré « réussi » sans exécution. Ils sont
 * écrits pour la CI (PostgreSQL requis) ; le sandbox de rédaction n'a ni PHP
 * ni base, donc ils n'ont pas été exécutés à l'écriture. Voir le rapport de lot.
 */
class PublicSyntheticDataTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PREFIX = 'pub-synth-';

    protected function setUp(): void
    {
        parent::setUp();

        // Un plan est requis : `companies.plan_id` est NOT NULL et référence
        // `plans`. On en crée un minimal plutôt que de dépendre de PlanSeeder,
        // pour que le test ne dépende pas de l'ordre des seeders.
        if (Schema::hasTable('plans') && DB::table('plans')->count() === 0) {
            // `public.plans` n'a PAS de colonne `updated_at` (migration
            // 2026_04_01_000001 : `created_at` seulement) — l'insérer ferait
            // échouer le setUp de TOUS les tests du fichier.
            DB::table('plans')->insert([
                'name' => 'Synthetic',
                'price_monthly' => 0,
                'created_at' => now(),
            ]);
        }
    }

    public function test_it_creates_one_tenant_per_configured_city(): void
    {
        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $cities = array_keys((array) config('public_synthetic.cities'));

        $this->assertCount(3, $cities, 'La configuration doit décrire trois villes.');

        foreach ($cities as $key) {
            // Le slug est normalisé par `Str::slug` dans la commande :
            // `wadi_nour` devient `wadi-nour`. Comparer la clé brute ferait
            // échouer ce test sur la ville arabe uniquement — un faux négatif
            // qui ne se verrait qu'en exécutant.
            $slug = self::PREFIX.\Illuminate\Support\Str::slug($key, '-');

            $this->assertDatabaseHas('companies', ['slug' => $slug]);
        }
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $companiesAfterFirst = Company::query()->where('slug', 'like', self::PREFIX.'%')->count();
        $branchesAfterFirst = $this->branchCount();

        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $this->assertSame(
            $companiesAfterFirst,
            Company::query()->where('slug', 'like', self::PREFIX.'%')->count(),
            'Rejouer la commande ne doit créer aucun tenant supplémentaire.',
        );

        $this->assertSame(
            $branchesAfterFirst,
            $this->branchCount(),
            'Rejouer la commande ne doit créer aucun établissement supplémentaire.',
        );
    }

    public function test_published_and_unpublished_establishments_coexist(): void
    {
        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $published = DB::table('shared_tenants.restaurant_branches')
            ->where('is_public', true)
            ->count();

        $unpublished = DB::table('shared_tenants.restaurant_branches')
            ->where('is_public', false)
            ->count();

        $this->assertGreaterThan(0, $published, 'Le jeu doit contenir des profils publiés.');
        $this->assertGreaterThan(
            0,
            $unpublished,
            'Le jeu doit contenir des profils NON publiés : sans eux, aucun test ne peut prouver '
            .'qu\'un profil non publié est absent de la découverte.',
        );
    }

    public function test_every_published_establishment_has_a_globally_unique_public_slug(): void
    {
        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $slugs = DB::table('shared_tenants.restaurant_branches')
            ->where('is_public', true)
            ->pluck('public_slug')
            ->all();

        $this->assertNotEmpty($slugs);
        $this->assertSame(
            count($slugs),
            count(array_unique($slugs)),
            'Deux établissements publiés partagent un slug public : la contrainte globale est violée.',
        );

        foreach ($slugs as $slug) {
            $this->assertNotNull($slug);
            $this->assertStringStartsWith(self::PREFIX, (string) $slug);
        }
    }

    public function test_unpublished_establishments_have_no_public_slug(): void
    {
        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $withSlug = DB::table('shared_tenants.restaurant_branches')
            ->where('is_public', false)
            ->whereNotNull('public_slug')
            ->count();

        $this->assertSame(
            0,
            $withSlug,
            'Un profil non publié ne doit pas porter de slug public : il serait adressable par URL.',
        );
    }

    public function test_it_writes_the_category_threshold_as_platform_data(): void
    {
        if (! Schema::hasTable('public.platform_settings')) {
            $this->markTestSkipped('public.platform_settings absente de cette base.');
        }

        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();

        $raw = DB::table('public.platform_settings')
            ->where('key', PlatformSettingsRepository::DISCOVERY_CATEGORY_MIN_PAGES)
            ->value('value');

        $this->assertNotNull($raw, 'Le seuil doit être écrit en base, pas laissé à une constante.');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('default', $decoded);
        $this->assertArrayHasKey('by_city', $decoded);
        $this->assertNotEmpty($decoded['by_city'], 'Le seuil doit varier par ville.');
    }

    public function test_clean_removes_only_prefixed_tenants(): void
    {
        // Un tenant qui NE porte PAS le préfixe : il ne doit jamais être touché.
        $real = Company::factory()->create(['slug' => 'client-reel-inedit']);

        $this->artisan('leopardo:public:synthetic-data')->assertSuccessful();
        $this->artisan('leopardo:public:synthetic-data', ['--clean' => true])->assertSuccessful();

        $this->assertSame(
            0,
            Company::query()->where('slug', 'like', self::PREFIX.'%')->count(),
            'Le nettoyage doit supprimer tous les tenants synthétiques.',
        );

        $this->assertDatabaseHas('companies', ['slug' => 'client-reel-inedit']);
        $this->assertNotNull($real->fresh(), 'Un tenant hors préfixe ne doit jamais être supprimé.');
    }

    public function test_it_refuses_an_unknown_city(): void
    {
        $this->artisan('leopardo:public:synthetic-data', ['--city' => 'ville-inexistante'])
            ->assertFailed();
    }

    public function test_it_limits_execution_to_one_city(): void
    {
        $this->artisan('leopardo:public:synthetic-data', ['--city' => 'valmora'])
            ->assertSuccessful();

        $this->assertDatabaseHas('companies', ['slug' => self::PREFIX.'valmora']);
        $this->assertDatabaseMissing('companies', ['slug' => self::PREFIX.'yesilova']);
    }

    /**
     * Les trois villes doivent porter trois scripts différents : c'est la
     * raison d'être du jeu (tester le RTL et le turc, pas seulement le latin).
     */
    public function test_the_three_cities_cover_three_scripts(): void
    {
        /** @var array<string, array<string, mixed>> $cities */
        $cities = (array) config('public_synthetic.cities');

        $scripts = array_map(
            static fn (array $city): string => (string) ($city['script'] ?? ''),
            array_values($cities),
        );

        $this->assertContains('latin', $scripts);
        $this->assertContains('arabic_rtl', $scripts);
        $this->assertContains('turkish', $scripts);
    }

    /**
     * Aucune ville ne doit être codée en dur dans la commande : la seule
     * source est la configuration. Ce test lit le fichier de commande et
     * échoue si un nom de ville de la configuration y apparaît littéralement.
     */
    public function test_no_city_name_is_hardcoded_in_the_command(): void
    {
        $source = (string) file_get_contents(
            app_path('Console/Commands/PublicSyntheticDataCommand.php')
        );

        /** @var array<string, array<string, mixed>> $cities */
        $cities = (array) config('public_synthetic.cities');

        foreach ($cities as $city) {
            $name = (string) ($city['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $this->assertStringNotContainsString(
                $name,
                $source,
                sprintf('La ville « %s » est codée en dur dans la commande : elle doit venir de la configuration.', $name),
            );
        }
    }

    private function branchCount(): int
    {
        if (! Schema::hasTable('restaurant_branches')) {
            return 0;
        }

        return DB::table('shared_tenants.restaurant_branches')->count();
    }
}
