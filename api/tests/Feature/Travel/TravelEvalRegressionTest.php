<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #EVAL — Tests de NON-RÉGRESSION des 4 correctifs de la recette « agence de
 * voyage » (évaluation leopardo-hr, verticale TravelAgency).
 *
 * Chaque test verrouille un défaut réellement constaté puis corrigé :
 *
 *  1. POLICIES — `travel.agent` / `travel.checkin` / `travel.reports` sont
 *     honorés via les grants du manifest, sans exiger le rôle manager.
 *     Avant : un vendeur/contrôleur non-manager recevait 403 et ne pouvait
 *     ni vendre au guichet, ni embarquer, ni consulter les rapports.
 *
 *  2. MANIFESTE PASSAGERS ANONYMISÉ — hors `principal`/`rh`, le manifeste
 *     n'expose plus `full_name`, `birth_date`, `document_type` ni
 *     `unit_price_minor` (fuite de PII à tout employé du tenant).
 *
 *  3. 422 DUPLICATE_RESOURCE — une violation d'unicité remontait en 500 avec
 *     la trace SQL ; elle renvoie désormais un 422 stable et lisible.
 *
 *  4. GRANTS À L'ACTIVATION — `ActivateTravelAgencyAction` appelle
 *     `SolutionPermissionInstaller` : les 4 permissions du manifest sont
 *     posées pour le(s) `principal` du tenant (idempotent).
 *
 * Les tests exercent les surfaces HTTP réelles (Sanctum + middleware tenant +
 * `module.travelagency`) et relisent l'état DEPUIS LA BASE, jamais l'instance
 * en mémoire.
 */
class TravelEvalRegressionTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    /**
     * Employé simple (non-manager) porteur d'un grant explicite du manifest.
     */
    private function employeeWithGrant(Company $company, string $moduleKey): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        $grant = new EmployeeModuleGrant([
            'employee_id' => $employee->id,
            'module_key' => $moduleKey,
        ]);
        $grant->company_id = $company->id;
        $grant->granted_by_employee_id = $employee->id;
        $grant->save();

        return $employee;
    }

    /**
     * Trajet publié + tarif + inventaire de sièges, dans le tenant.
     *
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTripWithPrice(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. POLICIES — grants du manifest honorés sans rôle manager
    // ─────────────────────────────────────────────────────────────────────

    public function test_travel_agent_grant_can_sell_at_the_counter(): void
    {
        $company = $this->company();
        $this->principal($company);
        $seller = $this->employeeWithGrant($company, 'travel.agent');

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'eval-agent-001',
            'passengers' => [
                ['full_name' => 'Awa Traoré', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.status', BookingStatus::PENDING->value);
    }

    public function test_employee_without_travel_agent_grant_cannot_sell(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $plain */
        $plain = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        Sanctum::actingAs($plain);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'eval-agent-002',
            'passengers' => [
                ['full_name' => 'Sans Grant', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(403);
    }

    public function test_travel_checkin_grant_can_check_in_a_ticket(): void
    {
        $company = $this->company();
        $this->principal($company);
        $controller = $this->employeeWithGrant($company, 'travel.checkin');

        $ticket = app(TenantManager::class)->withinTenant($company, function (): TravelTicket {
            $booking = TravelBooking::factory()->create(['status' => BookingStatus::CONFIRMED]);
            $passenger = $booking->passengers()->create([
                'full_name' => 'Moussa Diop',
                'age_category' => 'adult',
                'class_id' => TravelClass::factory()->create()->id,
                'unit_price_minor' => 15000,
            ]);

            return TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
            ]);
        });

        Sanctum::actingAs($controller);

        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');
    }

    public function test_employee_without_travel_checkin_grant_cannot_check_in(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $plain */
        $plain = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        $ticket = app(TenantManager::class)->withinTenant($company, function (): TravelTicket {
            $booking = TravelBooking::factory()->create(['status' => BookingStatus::CONFIRMED]);
            $passenger = $booking->passengers()->create([
                'full_name' => 'Sans Grant',
                'age_category' => 'adult',
                'class_id' => TravelClass::factory()->create()->id,
                'unit_price_minor' => 15000,
            ]);

            return TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
            ]);
        });

        Sanctum::actingAs($plain);

        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/check-in")->assertStatus(403);
    }

    public function test_travel_reports_grant_can_read_reports(): void
    {
        $company = $this->company();
        $this->principal($company);
        $analyst = $this->employeeWithGrant($company, 'travel.reports');

        Sanctum::actingAs($analyst);

        $this->getJson('/api/v1/travel/reports/sales?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())
            ->assertOk();
    }

    public function test_employee_without_travel_reports_grant_cannot_read_reports(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $plain */
        $plain = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        Sanctum::actingAs($plain);

        $this->getJson('/api/v1/travel/reports/sales?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. MANIFESTE PASSAGERS — anonymisation hors principal/rh
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array{trip: TravelTrip, passenger: TravelPassenger}
     */
    private function tripWithPassenger(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED,
            ]);

            $passenger = $booking->passengers()->create([
                'full_name' => 'Fatou Ndiaye',
                'birth_date' => '1990-04-12',
                'document_type' => 'national_id',
                'age_category' => 'adult',
                'class_id' => TravelClass::factory()->create()->id,
                'seat_number' => 7,
                'unit_price_minor' => 15000,
            ]);

            return ['trip' => $trip, 'passenger' => $passenger];
        });
    }

    public function test_manifest_is_anonymized_for_a_plain_employee(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $plain */
        $plain = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        ['trip' => $trip] = $this->tripWithPassenger($company);

        Sanctum::actingAs($plain);

        $response = $this->getJson("/api/v1/travel/trips/{$trip->id}/manifest")
            ->assertOk()
            ->assertJsonPath('anonymized', true)
            ->assertJsonCount(1, 'data');

        $row = $response->json('data.0');

        // Aucune PII ne doit sortir.
        self::assertArrayNotHasKey('full_name', $row);
        self::assertArrayNotHasKey('birth_date', $row);
        self::assertArrayNotHasKey('document_type', $row);
        self::assertArrayNotHasKey('unit_price_minor', $row);

        // Les champs d'exploitation restent disponibles.
        self::assertSame(7, $row['seat_number']);
        self::assertSame('adult', $row['age_category']);
    }

    public function test_manifest_exposes_full_pii_to_the_principal(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);

        ['trip' => $trip] = $this->tripWithPassenger($company);

        Sanctum::actingAs($principal);

        $response = $this->getJson("/api/v1/travel/trips/{$trip->id}/manifest")->assertOk();

        $row = $response->json('data.0');

        self::assertSame('Fatou Ndiaye', $row['full_name']);
        self::assertSame('1990-04-12', $row['birth_date']);
        self::assertSame('national_id', $row['document_type']);
        self::assertSame(15000, $row['unit_price_minor']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. 422 DUPLICATE_RESOURCE — violation d'unicité, jamais 500
    // ─────────────────────────────────────────────────────────────────────

    public function test_duplicate_station_code_returns_422_duplicate_resource(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            // `code` n'a AUCUNE règle `unique` applicative sur les gares : la
            // contrainte `travel_stations_company_code_unique` est la seule
            // barrière — c'est exactement le chemin qui produisait un 500.
            TravelStation::factory()->create(['code' => 'GARE-DUP']);

            return TravelCity::factory()->create()->id;
        });

        Sanctum::actingAs($principal);

        $this->postJson('/api/v1/travel/stations', [
            'code' => 'GARE-DUP',
            'name' => 'Gare doublon',
            'city_id' => $cityId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'DUPLICATE_RESOURCE')
            ->assertJsonPath('message', 'DUPLICATE_RESOURCE');

        // La réponse ne doit jamais divulguer la contrainte ni la requête SQL.
        $body = $this->postJson('/api/v1/travel/stations', [
            'code' => 'GARE-DUP',
            'name' => 'Gare doublon',
            'city_id' => $cityId,
        ])->getContent();

        self::assertStringNotContainsString('travel_stations_company_code_unique', (string) $body);
        self::assertStringNotContainsString('SQLSTATE', (string) $body);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. ACTIVATION — les grants du manifest sont installés
    // ─────────────────────────────────────────────────────────────────────

    public function test_activation_installs_manifest_permissions_for_the_principal(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $principal = $this->principal($company);

        // Fail-closed avant activation.
        self::assertSame(0, EmployeeModuleGrant::query()
            ->where('company_id', $company->id)
            ->count());

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            app(ActivateTravelAgencyAction::class)->execute($company);
        });

        // Relecture DEPUIS LA BASE.
        $keys = EmployeeModuleGrant::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $principal->id)
            ->orderBy('module_key')
            ->pluck('module_key')
            ->all();

        self::assertSame(
            ['travel.agent', 'travel.checkin', 'travel.manage', 'travel.reports'],
            $keys,
            'L\'activation doit installer les 4 permissions du manifest pour le principal.',
        );
    }

    public function test_activation_is_idempotent_on_grants(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $action = app(ActivateTravelAgencyAction::class);
            $action->execute($company);
            $action->execute($company);
        });

        self::assertSame(
            4,
            EmployeeModuleGrant::query()->where('company_id', $company->id)->count(),
            'Une seconde activation ne doit jamais dupliquer les grants.',
        );
    }

    public function test_activation_does_not_grant_permissions_to_plain_employees(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->principal($company);

        /** @var Employee $plain */
        $plain = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            app(ActivateTravelAgencyAction::class)->execute($company);
        });

        self::assertSame(
            0,
            EmployeeModuleGrant::query()
                ->where('company_id', $company->id)
                ->where('employee_id', $plain->id)
                ->count(),
            'Les grants d\'activation sont réservés au(x) principal(aux) du tenant.',
        );
    }
}
