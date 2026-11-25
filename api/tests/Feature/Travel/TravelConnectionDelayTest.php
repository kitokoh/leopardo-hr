<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Services\ConnectionDelayResolver;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * M5 (évaluation leopardo-hr) — temps de correspondance minimum PAR GARE.
 *
 * Avant M5, le délai était la constante `ConnectionSearchAction::MIN_CONNECTION_MINUTES`
 * (45) et un littéral `45` dans `TravelConnectionController`. Ces tests
 * verrouillent la nouvelle cascade de résolution :
 *
 *   surcharge de l'appelant → réglage de la gare de correspondance → défaut du tenant
 *
 * et surtout le comportement métier : deux gares du même réseau peuvent
 * imposer des délais différents, et la gare la plus exigeante d'une ville
 * gagne (une correspondance doit être tenable dans TOUTES les gares).
 */
class TravelConnectionDelayTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Horloge figée sur la date des trajets de ces tests.
     *
     * Les trajets sont datés en absolu ('2026-10-01') : sans horloge maîtrisée,
     * le test dépendrait du jour où il tourne (une date qui devient passée fait
     * échouer la recherche de correspondances) et la garde #8396 signalerait le
     * fichier. `setTestNow` rend ces littéraux relatifs à une origine fixée.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 06:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    /**
     * Trajet publié entre deux villes à une heure donnée.
     */
    private function tripBetween(
        Company $company,
        TravelCity $origin,
        TravelCity $destination,
        string $date,
        string $departure,
        string $arrival,
        int $price,
        string $code,
    ): TravelTrip {
        return app(TenantManager::class)->withinTenant($company, function () use ($origin, $destination, $date, $departure, $arrival, $price, $code): TravelTrip {
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);
            $class = TravelClass::factory()->create();

            $trip = TravelTrip::factory()->create([
                'code' => $code,
                'route_id' => $route->id,
                'departure_date' => $date,
                'departure_time' => $departure,
                'arrival_date' => $date,
                'arrival_time' => $arrival,
                'status' => 'published',
                'total_seats' => 40,
            ]);

            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => $price,
                'child_price_minor' => $price,
            ]);

            return $trip;
        });
    }

    /**
     * Trois villes A → B → C, avec une correspondance de 60 min à B.
     *
     * @return array{0: TravelCity, 1: TravelCity, 2: TravelCity}
     */
    private function threeCities(Company $company): array
    {
        /** @var array<int, TravelCity> $cities */
        $cities = app(TenantManager::class)->withinTenant($company, fn (): array => [
            TravelCity::factory()->create(['name' => 'Yaoundé']),
            TravelCity::factory()->create(['name' => 'Douala']),
            TravelCity::factory()->create(['name' => 'Bafoussam']),
        ]);

        return [$cities[0], $cities[1], $cities[2]];
    }

    // ---------------------------------------------------------------------
    // Résolveur — cascade de résolution
    // ---------------------------------------------------------------------

    public function test_resolver_falls_back_to_tenant_default_when_nothing_is_configured(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$yaounde, $douala] = $this->threeCities($company);

        config(['travel.connections.default_min_minutes' => 45]);

        $resolver = app(ConnectionDelayResolver::class);

        $this->assertSame(45, $resolver->resolve($douala->id));
        $this->assertNull($resolver->stationDelay($douala->id));
    }

    public function test_resolver_uses_the_station_setting_over_the_tenant_default(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        config(['travel.connections.default_min_minutes' => 45]);

        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create([
                'city_id' => $douala->id,
                'min_connection_minutes' => 90,
            ]);
        });

        $this->assertSame(90, app(ConnectionDelayResolver::class)->resolve($douala->id));
    }

    public function test_resolver_keeps_the_most_constraining_station_of_a_city(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 30]);
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 120]);
            // Une gare sans surcharge ne doit pas tirer le délai vers le bas.
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => null]);
        });

        $this->assertSame(120, app(ConnectionDelayResolver::class)->resolve($douala->id));
    }

    public function test_resolver_lets_the_caller_override_everything(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 120]);
        });

        $resolver = app(ConnectionDelayResolver::class);

        $this->assertSame(20, $resolver->resolve($douala->id, 20));
        // Hors bornes : ramené dans le domaine [15, 240].
        $this->assertSame(15, $resolver->resolve($douala->id, 1));
        $this->assertSame(240, $resolver->resolve($douala->id, 9999));
    }

    // ---------------------------------------------------------------------
    // Effet réel sur la recherche de correspondances
    // ---------------------------------------------------------------------

    public function test_station_delay_excludes_a_connection_that_the_default_would_accept(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$yaounde, $douala, $bafoussam] = $this->threeCities($company);

        config(['travel.connections.default_min_minutes' => 45]);

        // 60 min de correspondance : acceptée avec le défaut de 45.
        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $douala, $bafoussam, '2026-10-01', '11:00', '13:00', 8000, 'LEG-B');

        $url = '/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id
            .'&destination_city_id='.$bafoussam->id.'&date=2026-10-01';

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');

        // La gare de Douala impose 90 min → la même correspondance est exclue.
        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 90]);
        });

        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_station_delay_accepts_a_connection_that_the_default_would_reject(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$yaounde, $douala, $bafoussam] = $this->threeCities($company);

        config(['travel.connections.default_min_minutes' => 45]);

        // 20 min de correspondance : refusée avec le défaut de 45.
        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $douala, $bafoussam, '2026-10-01', '10:20', '12:20', 8000, 'LEG-B');

        $url = '/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id
            .'&destination_city_id='.$bafoussam->id.'&date=2026-10-01';

        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');

        // Gare de correspondance peu exigeante (15 min) → la correspondance passe.
        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 15]);
        });

        $this->getJson($url)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.connection_minutes', 20);
    }

    public function test_shop_connections_endpoint_honours_the_station_delay(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$yaounde, $douala, $bafoussam] = $this->threeCities($company);

        config(['travel.connections.default_min_minutes' => 45]);

        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $douala, $bafoussam, '2026-10-01', '11:00', '13:00', 8000, 'LEG-B');

        $url = '/api/v1/travel/shop/connections?origin_city_id='.$yaounde->id
            .'&destination_city_id='.$bafoussam->id.'&date=2026-10-01';

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');

        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 90]);
        });

        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_caller_override_wins_over_the_station_delay(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$yaounde, $douala, $bafoussam] = $this->threeCities($company);

        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $douala, $bafoussam, '2026-10-01', '11:00', '13:00', 8000, 'LEG-B');

        app(TenantManager::class)->withinTenant($company, function () use ($douala): void {
            TravelStation::factory()->create(['city_id' => $douala->id, 'min_connection_minutes' => 90]);
        });

        $base = '/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id
            .'&destination_city_id='.$bafoussam->id.'&date=2026-10-01';

        // La gare impose 90 min → exclue.
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'data');

        // L'appelant demande explicitement 30 min → acceptée.
        $this->getJson($base.'&min_connection_minutes=30')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_out_of_range_override_is_rejected_with_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [$yaounde, , $bafoussam] = $this->threeCities($company);

        $base = '/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id
            .'&destination_city_id='.$bafoussam->id.'&date=2026-10-01';

        $this->getJson($base.'&min_connection_minutes=5')->assertStatus(422);
        $this->getJson($base.'&min_connection_minutes=500')->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // Réglage de la gare via l'API
    // ---------------------------------------------------------------------

    public function test_station_delay_is_settable_and_clearable_through_the_api(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        $station = app(TenantManager::class)->withinTenant($company, fn (): TravelStation => TravelStation::factory()->create([
            'city_id' => $douala->id,
            'min_connection_minutes' => null,
        ]));

        $this->putJson('/api/v1/travel/stations/'.$station->id, ['min_connection_minutes' => 75])
            ->assertOk()
            ->assertJsonPath('data.min_connection_minutes', 75);

        $this->assertSame(75, app(ConnectionDelayResolver::class)->resolve($douala->id));

        // null retire la surcharge : retour au défaut du tenant.
        $this->putJson('/api/v1/travel/stations/'.$station->id, ['min_connection_minutes' => null])
            ->assertOk()
            ->assertJsonPath('data.min_connection_minutes', null);

        $this->assertNull(app(ConnectionDelayResolver::class)->stationDelay($douala->id));
    }

    public function test_station_delay_outside_the_domain_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        $station = app(TenantManager::class)->withinTenant($company, fn (): TravelStation => TravelStation::factory()->create([
            'city_id' => $douala->id,
        ]));

        $this->putJson('/api/v1/travel/stations/'.$station->id, ['min_connection_minutes' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('min_connection_minutes');

        $this->putJson('/api/v1/travel/stations/'.$station->id, ['min_connection_minutes' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors('min_connection_minutes');
    }

    public function test_station_delay_is_exposed_in_the_station_resource(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        $station = app(TenantManager::class)->withinTenant($company, fn (): TravelStation => TravelStation::factory()->create([
            'city_id' => $douala->id,
            'min_connection_minutes' => 60,
        ]));

        $this->getJson('/api/v1/travel/stations/'.$station->id)
            ->assertOk()
            ->assertJsonPath('data.min_connection_minutes', 60);
    }

    // ─── Cache du résolveur ────────────────────────────────────────────────

    /**
     * Le cache ne doit PAS servir une valeur périmée après une écriture.
     *
     * C'est le seul risque réel d'une mémoïsation : une requête qui modifie le
     * réglage d'une gare puis relit une recherche dans la foulée. Le CRUD des
     * gares appelle `forget()`, ce test le verrouille.
     */
    public function test_updating_a_station_invalidates_the_resolver_cache(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        [, $douala] = $this->threeCities($company);

        $station = app(TenantManager::class)->withinTenant($company, fn (): TravelStation => TravelStation::factory()->create([
            'city_id' => $douala->id,
            'min_connection_minutes' => 60,
        ]));

        $resolver = app(ConnectionDelayResolver::class);

        // 1. Première lecture : met la ville en cache.
        $this->assertSame(60, $resolver->stationDelay((int) $douala->id));

        // 2. Écriture par l'API (le contrôleur doit invalider).
        $this->putJson('/api/v1/travel/stations/'.$station->id, ['min_connection_minutes' => 120])
            ->assertOk();

        // 3. La même instance ne doit plus servir 60.
        $this->assertSame(
            120,
            $resolver->stationDelay((int) $douala->id),
            'Le cache du résolveur a servi une valeur périmée après mise à jour de la gare.',
        );
    }

    /**
     * Une recherche dense ne doit pas relire `travel_stations` par paire
     * candidate : c'est tout l'objet du cache.
     *
     * On compte les requêtes SQL émises par `resolve()` sur N villes
     * distinctes, appelées plusieurs fois chacune.
     */
    public function test_resolver_reads_each_city_once_per_request(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cities = app(TenantManager::class)->withinTenant($company, function (): array {
            $created = [];

            for ($i = 0; $i < 5; $i++) {
                $city = TravelCity::factory()->create();
                TravelStation::factory()->create([
                    'city_id' => $city->id,
                    'min_connection_minutes' => 30 + $i,
                ]);
                $created[] = (int) $city->id;
            }

            return $created;
        });

        $resolver = app(ConnectionDelayResolver::class);

        $queries = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
            $queries++;
        });

        // 5 villes × 10 appels = 50 résolutions, comme une recherche dense.
        for ($round = 0; $round < 10; $round++) {
            foreach ($cities as $cityId) {
                $resolver->resolve($cityId);
            }
        }

        $this->assertSame(
            5,
            $queries,
            sprintf('Attendu 5 requêtes (une par ville), obtenu %d : le cache ne joue pas.', $queries),
        );
    }
}
