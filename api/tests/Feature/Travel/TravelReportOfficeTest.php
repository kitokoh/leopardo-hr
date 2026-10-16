<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8545 (TRAVEL-OS) — vue consolidée par agence.
 *
 * Couvre les critères d'acceptation : ventilation des ventes et annulations par
 * bureau de vente, ligne `unassigned` EXPLICITE pour les ventes en ligne (jamais
 * absorbées dans une agence), `totals` = somme des lignes, filtre `office_id`
 * borné au tenant (cross-tenant → 422), et non-régression de l'agrégat
 * entreprise sans filtre.
 */
class TravelReportOfficeTest extends TestCase
{
    use RefreshTenantDatabase;

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
     * @return array{trip: TravelTrip, offices: array<string, TravelOffice>}
     */
    private function fixture(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $origin = TravelCity::factory()->create(['name' => 'Douala']);
            $destination = TravelCity::factory()->create(['name' => 'Yaounde']);

            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);

            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 40,
            ]);

            $officeA = TravelOffice::factory()->create(['name' => 'Bureau Douala']);
            $officeB = TravelOffice::factory()->create(['name' => 'Bureau Yaounde']);

            return ['trip' => $trip, 'offices' => ['a' => $officeA, 'b' => $officeB]];
        });
    }

    private function booking(Company $company, int $tripId, ?int $officeId, int $amountMinor, int $passengers = 1, ?BookingStatus $status = null): TravelBooking
    {
        return app(TenantManager::class)->withinTenant($company, fn (): TravelBooking => TravelBooking::factory()->create([
            'trip_id' => $tripId,
            'office_id' => $officeId,
            'total_amount_minor' => $amountMinor,
            'passenger_count' => $passengers,
            'status' => ($status ?? BookingStatus::CONFIRMED)->value,
        ]));
    }

    public function test_ventilation_by_office_and_explicit_unassigned_row(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fixture = $this->fixture($company);
        $officeA = (int) $fixture['offices']['a']->id;
        $officeB = (int) $fixture['offices']['b']->id;

        // Guichet A : 2 ventes ; guichet B : 1 vente ; en ligne : 1 vente.
        $this->booking($company, (int) $fixture['trip']->id, $officeA, 10000, 2);
        $this->booking($company, (int) $fixture['trip']->id, $officeA, 5000);
        $this->booking($company, (int) $fixture['trip']->id, $officeB, 7000);
        $this->booking($company, (int) $fixture['trip']->id, null, 3000);

        // 1 annulation au guichet A.
        $this->booking($company, (int) $fixture['trip']->id, $officeA, 9000, 1, BookingStatus::CANCELLED);

        $response = $this->getJson($this->url())->assertOk();

        /** @var array<int, array<string, mixed>> $rowsData */
        $rowsData = $response->json('data.offices');

        $rowA = $this->officeRow($rowsData, $officeA);
        $rowB = $this->officeRow($rowsData, $officeB);

        // Agence A : 2 ventes nettes, 3 passagers, 15 000 ; 1 annulation.
        $this->assertSame(2, $rowA['bookings_count']);
        $this->assertSame(3, $rowA['passengers_count']);
        $this->assertSame(15000, $rowA['revenue_minor']);
        $this->assertSame(1, $rowA['cancellations_count']);

        // Agence B : 1 vente.
        $this->assertSame(1, $rowB['bookings_count']);
        $this->assertSame(7000, $rowB['revenue_minor']);

        // Ligne EXPLICITE pour les ventes en ligne — jamais absorbée.
        $unassigned = $this->unassignedRow($rowsData);
        $this->assertNull($unassigned['office_id']);
        $this->assertSame(1, $unassigned['bookings_count']);
        $this->assertSame(3000, $unassigned['revenue_minor']);

        // `totals` = somme des lignes (aucune vente perdue).
        $this->assertSame(4, $response->json('data.totals.bookings_count'));
        $this->assertSame(25000, $response->json('data.totals.revenue_minor'));
        $this->assertSame(1, $response->json('data.totals.cancellations_count'));
    }

    public function test_office_filter_narrows_sales_and_revenue(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fixture = $this->fixture($company);
        $officeA = (int) $fixture['offices']['a']->id;
        $officeB = (int) $fixture['offices']['b']->id;

        $this->booking($company, (int) $fixture['trip']->id, $officeA, 11000);
        $this->booking($company, (int) $fixture['trip']->id, $officeB, 22000);

        $this->getJson($this->url(['office_id' => $officeB], 'sales'))
            ->assertOk()
            ->assertJsonPath('data.bookings_count', 1)
            ->assertJsonPath('data.revenue_minor', 22000)
            ->assertJsonPath('data.filters.office_id', $officeB);
    }

    public function test_revenue_is_filtered_by_office(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fixture = $this->fixture($company);
        $officeA = (int) $fixture['offices']['a']->id;
        $officeB = (int) $fixture['offices']['b']->id;

        $bookingA = $this->booking($company, (int) $fixture['trip']->id, $officeA, 11000);
        $bookingB = $this->booking($company, (int) $fixture['trip']->id, $officeB, 22000);

        app(TenantManager::class)->withinTenant($company, function () use ($bookingA, $bookingB): void {
            TravelPayment::factory()->create([
                'booking_id' => $bookingA->id,
                'amount_minor' => 11000,
                'status' => 'confirmed',
            ]);
            TravelPayment::factory()->create([
                'booking_id' => $bookingB->id,
                'amount_minor' => 22000,
                'status' => 'confirmed',
            ]);
        });

        $this->getJson($this->url(['office_id' => $officeA], 'revenue'))
            ->assertOk()
            ->assertJsonPath('data.confirmed_minor', 11000)
            ->assertJsonPath('data.office_id', $officeA);
    }

    public function test_enterprise_aggregate_without_filter_is_unchanged(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fixture = $this->fixture($company);

        $this->booking($company, (int) $fixture['trip']->id, (int) $fixture['offices']['a']->id, 11000);
        $this->booking($company, (int) $fixture['trip']->id, null, 3000);

        // Aucun filtre : total entreprise = 2 ventes / 14 000 (non-régression).
        $this->getJson($this->url([], 'sales'))
            ->assertOk()
            ->assertJsonPath('data.bookings_count', 2)
            ->assertJsonPath('data.revenue_minor', 14000)
            ->assertJsonPath('data.filters.office_id', null);
    }

    public function test_cross_tenant_office_filter_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyB);

        $foreignOffice = app(TenantManager::class)->withinTenant(
            $companyB,
            fn (): TravelOffice => TravelOffice::factory()->create(['name' => 'Bureau etranger']),
        );

        $this->getJson($this->url(['office_id' => (int) $foreignOffice->id], 'sales'))->assertStatus(422);
    }

    public function test_offices_report_forbidden_for_simple_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson($this->url())->assertStatus(403);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function url(array $extra = [], string $report = 'offices'): string
    {
        $params = array_merge([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ], $extra);

        return '/api/v1/travel/reports/'.$report.'?'.http_build_query($params);
    }

    /**
     * Extrait la ligne d'une agence — échoue explicitement si elle est absente
     * (jamais d'accès silencieux à un offset inexistant).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function officeRow(array $rows, int $officeId): array
    {
        foreach ($rows as $row) {
            if (($row['office_id'] ?? null) === $officeId) {
                return $row;
            }
        }

        $this->fail("Agence #$officeId absente de la ventilation.");
    }

    /**
     * Ligne explicite des ventes sans agence (ventes en ligne).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function unassignedRow(array $rows): array
    {
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === 'Sans agence (ventes en ligne)') {
                return $row;
            }
        }

        $this->fail('Ligne « Sans agence (ventes en ligne) » absente de la ventilation.');
    }
}
