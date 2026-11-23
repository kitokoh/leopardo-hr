<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelHotelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoomInventory;
use Symfony\Component\Process\Process;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * M4 — CONCURRENCE RÉELLE sur l'inventaire hôtelier.
 *
 * `TravelHotelInventoryTest` prouve le 409 en séquentiel : la seconde
 * réservation voit le stock déjà décrémenté. Cela ne prouve PAS que le verrou
 * `FOR UPDATE` de `BookHotelRoomAction` fonctionne : en séquentiel, aucune
 * course n'a lieu, et une implémentation sans verrou passerait le test.
 *
 * Ici, deux PROCESSUS OS distincts (deux `php artisan` lancés en parallèle)
 * tentent de réserver la DERNIÈRE chambre disponible au même instant. Le
 * contrat vérifié :
 *
 *   - exactement UNE réservation est créée (jamais deux) ;
 *   - l'inventaire n'est jamais surréservé (`rooms_booked <= rooms_total`) ;
 *   - le perdant échoue proprement (409), jamais en 500 ni en violation de
 *     contrainte brute.
 *
 * Pourquoi pas `RefreshTenantDatabase` tel quel : les processus enfants ont
 * leur propre connexion PDO. Une transaction de test non validée leur serait
 * invisible, et le rollback final effacerait leurs écritures. On réutilise
 * donc la logique de migration du dépôt (public + tenant, chemins corrects)
 * en neutralisant UNIQUEMENT la transaction englobante : les données sont
 * réellement commitées, donc visibles des deux côtés — c'est la seule façon
 * de tester une vraie course.
 */
class TravelHotelInventoryConcurrencyTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Neutralise la transaction de `RefreshDatabase` : les écritures du test
     * doivent être commitées pour être visibles des processus concurrents.
     */
    protected function beginDatabaseTransaction(): void
    {
        // Volontairement vide — voir le commentaire de classe.
    }

    private Company $company;

    private Employee $actor;

    private TravelHotel $hotel;

    private TravelHotelRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $company->setFeature('travelagency', true);
        $company->save();

        // Le worker enfant résout un manager du tenant : il doit exister en
        // base, commité, pour être visible depuis son propre processus.
        /** @var Employee $actor */
        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->actor = $actor;

        [$this->hotel, $this->room] = app(TenantManager::class)->withinTenant($company, function (): array {
            $city = TravelCity::factory()->create();
            $hotel = TravelHotel::factory()->create(['city_id' => $city->id]);
            $room = TravelHotelRoom::factory()->create([
                'hotel_id' => $hotel->id,
                'room_number' => '101',
                'capacity' => 2,
                'price_per_night_minor' => 20000,
                'currency' => 'XAF',
            ]);

            return [$hotel, $room];
        });
    }

    /**
     * Deux processus concurrents, une seule chambre : un seul gagnant.
     */
    public function test_two_concurrent_processes_cannot_oversell_the_last_room(): void
    {
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(11)->toDateString();

        // Capacité 1 pour cette nuit : c'est LA dernière chambre.
        app(TenantManager::class)->withinTenant($this->company, function () use ($checkIn): void {
            TravelHotelRoomInventory::query()->create([
                'room_id' => $this->room->id,
                'stay_date' => $checkIn,
                'rooms_total' => 1,
                'rooms_booked' => 0,
            ]);
        });

        $results = $this->runConcurrentBookings($checkIn, $checkOut, ['race-a', 'race-b']);

        $statuses = array_map(fn (array $r): int => $r['status'], $results);
        sort($statuses);

        // Un 201 (gagnant) et un 409 (perdant) — jamais deux 201, jamais un 500.
        $this->assertSame([201, 409], $statuses, 'Expected exactly one winner (201) and one clean rejection (409). Raw output: '.json_encode($results));

        // Une seule réservation en base.
        $bookings = TravelHotelBooking::query()->where('room_id', $this->room->id)->count();
        $this->assertSame(1, $bookings, 'Exactly one booking must exist after the race.');

        // L'inventaire n'est pas surréservé.
        $inventory = TravelHotelRoomInventory::query()
            ->where('room_id', $this->room->id)
            ->whereDate('stay_date', $checkIn)
            ->firstOrFail();

        $this->assertSame(1, $inventory->rooms_booked, 'Inventory must be consumed exactly once.');
        $this->assertLessThanOrEqual($inventory->rooms_total, $inventory->rooms_booked);
    }

    /**
     * Deux processus, deux chambres disponibles : les deux passent.
     *
     * Contre-épreuve du test précédent — sans elle, un verrou trop large (qui
     * sérialise TOUT et refuse le second) passerait pour un correctif.
     */
    public function test_two_concurrent_processes_both_succeed_when_capacity_allows(): void
    {
        $checkIn = now()->addDays(20)->toDateString();
        $checkOut = now()->addDays(21)->toDateString();

        app(TenantManager::class)->withinTenant($this->company, function () use ($checkIn): void {
            TravelHotelRoomInventory::query()->create([
                'room_id' => $this->room->id,
                'stay_date' => $checkIn,
                'rooms_total' => 2,
                'rooms_booked' => 0,
            ]);
        });

        $results = $this->runConcurrentBookings($checkIn, $checkOut, ['race-c', 'race-d']);

        $statuses = array_map(fn (array $r): int => $r['status'], $results);
        sort($statuses);

        $this->assertSame([201, 201], $statuses, 'Both bookings must succeed when capacity allows. Raw output: '.json_encode($results));

        $this->assertSame(2, TravelHotelBooking::query()->where('room_id', $this->room->id)->count());

        $inventory = TravelHotelRoomInventory::query()
            ->where('room_id', $this->room->id)
            ->whereDate('stay_date', $checkIn)
            ->firstOrFail();

        $this->assertSame(2, $inventory->rooms_booked);
    }

    /**
     * Lance N processus `php artisan` en parallèle, chacun réservant la même
     * chambre sur le même intervalle avec sa propre clé d'idempotence.
     *
     * @param  list<string>  $idempotencyKeys
     * @return list<array{status: int, body: string}>
     */
    private function runConcurrentBookings(string $checkIn, string $checkOut, array $idempotencyKeys): array
    {
        $script = base_path('tests/Support/travel_hotel_race_worker.php');

        $processes = [];

        foreach ($idempotencyKeys as $key) {
            $process = new Process(
                [
                    PHP_BINARY,
                    $script,
                    (string) $this->company->id,
                    (string) $this->hotel->id,
                    (string) $this->room->id,
                    $checkIn,
                    $checkOut,
                    $key,
                ],
                base_path(),
                $this->workerEnvironment(),
                timeout: 60,
            );

            $process->start();
            $processes[] = $process;
        }

        $results = [];

        foreach ($processes as $process) {
            $process->wait();

            $output = trim($process->getOutput());
            $decoded = json_decode($output, true);

            if (! is_array($decoded) || ! isset($decoded['status'])) {
                $this->fail(sprintf(
                    "Race worker produced no usable result.\nEXIT: %d\nSTDOUT:\n%s\nSTDERR:\n%s",
                    (int) $process->getExitCode(),
                    $output,
                    $process->getErrorOutput(),
                ));
            }

            $results[] = ['status' => (int) $decoded['status'], 'body' => (string) ($decoded['body'] ?? '')];
        }

        return $results;
    }

    /**
     * Environnement des processus enfants : la MÊME base que le test, pour
     * qu'ils voient et modifient les lignes réellement commitées.
     *
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) config('database.default'),
        ];

        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SEARCH_PATH'] as $key) {
            $value = env($key);

            if ($value !== null) {
                $env[$key] = (string) $value;
            }
        }

        return $env;
    }
}
