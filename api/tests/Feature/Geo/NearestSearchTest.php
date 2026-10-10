<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Geo\Domain\Exceptions\UnknownSearchableTypeException;
use App\Modules\Geo\Infrastructure\Services\SearchableRegistry;
use App\Shared\Contracts\Geo\GeoLocatable;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * GEO-04 (#8353, BC-33 GEO) — moteur « le plus proche » sur un modèle
 * factice enregistré : tri par distance, borne de rayon, isolation tenant.
 *
 * Tourne sur les deux stratégies (PostGIS en CI PG16, repli Haversine
 * ailleurs) : le contrat de résultat est identique.
 */
class NearestSearchTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('geo_fake_places', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('name', 120);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $this->companyB = $companyB;

        /** @var SearchableRegistry $registry */
        $registry = $this->app->make(SearchableRegistry::class);
        $registry->register('fake_place', FakeNearestPlace::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('geo_fake_places');

        parent::tearDown();
    }

    public function test_nearest_returns_closest_first_within_radius(): void
    {
        $this->seedPlaces();

        /** @var GeoServiceContract $geo */
        $geo = $this->app->make(GeoServiceContract::class);
        $center = new GeoPoint(48.8566, 2.3522); // Paris (Notre-Dame)

        $results = $this->withTenantContext(
            $this->companyA,
            fn (): array => $geo->nearest('fake_place', $center, 5.0, 10)
        );

        self::assertCount(2, $results);
        // Tri croissant : « Proche » (~60 m) avant « Un peu plus loin » (~800 m).
        self::assertSame('Proche', $results[0]->locatable->geoLabel());
        self::assertSame('Un peu plus loin', $results[1]->locatable->geoLabel());
        self::assertLessThan($results[1]->distance->meters(), $results[0]->distance->meters());
        self::assertLessThan(5000, $results[1]->distance->meters());
        // Représentation API stable (GEO-05).
        self::assertSame('Proche', $results[0]->toArray()['label']);
        self::assertNotNull($results[0]->toArray()['id']);
    }

    public function test_nearest_never_leaks_other_tenants(): void
    {
        $this->seedPlaces();

        /** @var GeoServiceContract $geo */
        $geo = $this->app->make(GeoServiceContract::class);
        $center = new GeoPoint(48.8566, 2.3522);

        $results = $this->withTenantContext(
            $this->companyA,
            fn (): array => $geo->nearest('fake_place', $center, 50.0, 10)
        );

        $labels = array_map(
            static fn (\App\Shared\Geo\NearestResult $result): string => $result->locatable->geoLabel(),
            $results
        );

        self::assertNotContains('Place du tenant B', $labels);
    }

    public function test_nearest_unknown_type_is_refused(): void
    {
        /** @var GeoServiceContract $geo */
        $geo = $this->app->make(GeoServiceContract::class);

        $this->expectException(UnknownSearchableTypeException::class);

        $geo->nearest('type_inconnu', new GeoPoint(48.8566, 2.3522));
    }

    public function test_nearest_excludes_models_without_position(): void
    {
        $this->withTenantContext($this->companyA, function (): void {
            FakeNearestPlace::create(['name' => 'Sans position', 'latitude' => null, 'longitude' => null]);
            FakeNearestPlace::create(['name' => 'Proche', 'latitude' => 48.8570, 'longitude' => 2.3530]);
        });

        /** @var GeoServiceContract $geo */
        $geo = $this->app->make(GeoServiceContract::class);

        $results = $this->withTenantContext(
            $this->companyA,
            fn (): array => $geo->nearest('fake_place', new GeoPoint(48.8566, 2.3522), 5.0, 10)
        );

        self::assertCount(1, $results);
        self::assertSame('Proche', $results[0]->locatable->geoLabel());
    }

    private function seedPlaces(): void
    {
        $this->withTenantContext($this->companyA, function (): void {
            // ≈ 60 m du centre.
            FakeNearestPlace::create(['name' => 'Proche', 'latitude' => 48.8570, 'longitude' => 2.3530]);
            // ≈ 800 m du centre.
            FakeNearestPlace::create(['name' => 'Un peu plus loin', 'latitude' => 48.8630, 'longitude' => 2.3600]);
            // Lyon (~392 km) — hors rayon.
            FakeNearestPlace::create(['name' => 'Tres loin', 'latitude' => 45.7640, 'longitude' => 4.8357]);
        });

        $this->withTenantContext($this->companyB, function (): void {
            FakeNearestPlace::create(['name' => 'Place du tenant B', 'latitude' => 48.8570, 'longitude' => 2.3530]);
        });
    }
}

/**
 * Modèle factice GeoLocatable pour la registry (table créée par le test).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property float|null $latitude
 * @property float|null $longitude
 */
class FakeNearestPlace extends Model implements GeoLocatable
{
    use BelongsToCompany;

    protected $table = 'geo_fake_places';

    protected $fillable = ['name', 'latitude', 'longitude'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function geoPoint(): ?GeoPoint
    {
        return GeoPoint::fromNullable($this->latitude, $this->longitude);
    }

    public function geoLabel(): string
    {
        return $this->name;
    }

    public static function geoLatitudeColumn(): string
    {
        return 'latitude';
    }

    public static function geoLongitudeColumn(): string
    {
        return 'longitude';
    }
}
