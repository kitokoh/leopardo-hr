<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Contracts\Geo\GeoLocatable;
use App\Shared\Geo\GeoPoint;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * GEO-05 (#8354, BC-33 GEO) — endpoint `GET /api/v1/geo/nearest`.
 *
 *   - 401 sans authentification ;
 *   - 403 quand le feature flag `geo` est inactif ;
 *   - 422 sur paramètres invalides (type, bornes, rayon) ;
 *   - 422 GEO_UNKNOWN_SEARCHABLE_TYPE sur type non enregistré (fail-closed) ;
 *   - 200 liste triée par distance croissante, isolation tenant respectée.
 */
class GeoNearestApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('geo_api_fake_places', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('name', 120);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        config()->set('geo.searchables', ['fake_place' => GeoApiFakePlace::class]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('geo_api_fake_places');

        parent::tearDown();
    }

    public function test_nearest_requires_authentication(): void
    {
        $this->getJson('/api/v1/geo/nearest?type=fake_place&lat=48.8566&lng=2.3522')
            ->assertStatus(401);
    }

    public function test_nearest_is_rejected_when_feature_flag_disabled(): void
    {
        $company = $this->createCompany();

        $this->actingAsManager($company);

        $this->getJson('/api/v1/geo/nearest?type=fake_place&lat=48.8566&lng=2.3522')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_nearest_validates_parameters(): void
    {
        $company = $this->createCompany(enableGeo: true);

        $this->actingAsManager($company);

        // Type manquant, coordonnées manquantes.
        $this->getJson('/api/v1/geo/nearest')->assertStatus(422);

        // Latitude hors bornes.
        $this->getJson('/api/v1/geo/nearest?type=fake_place&lat=91&lng=2.35')->assertStatus(422);

        // Rayon hors bornes (max config 50 km).
        $this->getJson('/api/v1/geo/nearest?type=fake_place&lat=48.85&lng=2.35&radius_km=500')->assertStatus(422);

        // Type non slug (injection d'identifiant refusée).
        $this->getJson('/api/v1/geo/nearest?type=Fake%20Place&lat=48.85&lng=2.35')->assertStatus(422);
    }

    public function test_nearest_unknown_type_is_refused_fail_closed(): void
    {
        $company = $this->createCompany(enableGeo: true);

        $this->actingAsManager($company);

        $this->getJson('/api/v1/geo/nearest?type=pharmacy&lat=48.8566&lng=2.3522')
            ->assertStatus(422)
            ->assertJson(['error' => 'GEO_UNKNOWN_SEARCHABLE_TYPE']);
    }

    public function test_nearest_returns_closest_first_and_never_leaks_other_tenants(): void
    {
        $companyA = $this->createCompany(enableGeo: true);
        $companyB = $this->createCompany(enableGeo: true);

        $this->seedPlaces($companyA, $companyB);

        $this->actingAsManager($companyA);

        $response = $this->getJson('/api/v1/geo/nearest?type=fake_place&lat=48.8566&lng=2.3522&radius_km=5')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        /** @var list<string> $labels */
        $labels = $response->json('data.*.label');

        // Tri croissant : « Proche » (~60 m) avant « Un peu plus loin » (~800 m).
        self::assertSame(['Proche', 'Un peu plus loin'], $labels);
        // Isolation tenant : la place du tenant B (même position) n'apparaît pas.
        self::assertNotContains('Place du tenant B', $labels);
    }

    private function createCompany(bool $enableGeo = false): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableGeo) {
            $company->setFeature('geo', true);
            $company->save();
        }

        return $company;
    }

    private function actingAsManager(Company $company): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);
    }

    private function seedPlaces(Company $companyA, Company $companyB): void
    {
        $this->withTenantContext($companyA, function (): void {
            // ≈ 60 m du centre (Paris, Notre-Dame).
            GeoApiFakePlace::create(['name' => 'Proche', 'latitude' => 48.8570, 'longitude' => 2.3530]);
            // ≈ 800 m du centre.
            GeoApiFakePlace::create(['name' => 'Un peu plus loin', 'latitude' => 48.8630, 'longitude' => 2.3600]);
            // Lyon (~392 km) — hors rayon.
            GeoApiFakePlace::create(['name' => 'Tres loin', 'latitude' => 45.7640, 'longitude' => 4.8357]);
        });

        $this->withTenantContext($companyB, function (): void {
            GeoApiFakePlace::create(['name' => 'Place du tenant B', 'latitude' => 48.8570, 'longitude' => 2.3530]);
        });
    }
}

/**
 * Modèle factice GeoLocatable enregistré pour les tests HTTP (table créée
 * par le test — aucune dépendance à une verticale réelle).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property float|null $latitude
 * @property float|null $longitude
 */
class GeoApiFakePlace extends Model implements GeoLocatable
{
    use BelongsToCompany;

    protected $table = 'geo_api_fake_places';

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
