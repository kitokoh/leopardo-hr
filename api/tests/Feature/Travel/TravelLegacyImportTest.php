<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\LegacyTravelImportService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1003 (#6116) — Import des données legacy gv-back.
 *
 * Couvre le critère d'acceptation : import REJOUABLE sans doublon et
 * rapport complet ; dry-run sans écriture ; mapping (minor units, enums).
 */
class TravelLegacyImportTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function dump(): array
    {
        return [
            'generated_at' => '2026-01-15T10:00:00Z',
            'carriers' => [['code' => 'C1', 'name' => 'Compagnie 1', 'type' => 'bus']],
            'routes' => [['external_id' => 'R1', 'code' => 'R1', 'origin_city_code' => 'Douala', 'destination_city_code' => 'Yaoundé']],
            'trips' => [[
                'external_id' => 'T1',
                'route_external_id' => 'R1',
                'carrier_code' => 'C1',
                'departure_date' => '2026-03-01',
                'departure_time' => '08:00:00',
                'arrival_date' => '2026-03-01',
                'arrival_time' => '12:00:00',
                'total_seats' => 40,
                'status' => 'scheduled',
                'prices' => [['class_code' => 'ECO', 'adult_amount' => 15000, 'child_amount' => 8000, 'currency' => 'XAF']],
            ]],
            'bookings' => [[
                'legacy_id' => 'B1',
                'trip_external_id' => 'T1',
                'status' => 'confirmed',
                'total_amount' => 15000,
                'currency' => 'XAF',
                'passengers' => [['full_name' => 'Jean Dupont', 'age_category' => 'adult']],
            ]],
        ];
    }

    private function seedCities(Company $company): void
    {
        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelCity::factory()->create(['name' => 'Douala']);
            TravelCity::factory()->create(['name' => 'Yaoundé']);
        });
    }

    public function test_import_is_idempotent_and_complete(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->seedCities($company);
        $service = app(LegacyTravelImportService::class);

        $first = app(TenantManager::class)->withinTenant($company, fn () => $service->import($company->id, $this->dump()));
        $second = app(TenantManager::class)->withinTenant($company, fn () => $service->import($company->id, $this->dump()));

        // Rapport complet.
        $this->assertSame(1, $first['carriers']);
        $this->assertSame(1, $first['routes']);
        $this->assertSame(1, $first['trips']);
        $this->assertSame(1, $first['prices']);
        $this->assertSame(1, $first['bookings']);

        // Rejeu : aucun doublon (mêmes comptes, pas de nouvelles lignes).
        $this->assertSame(1, TravelCarrier::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, TravelRoute::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, TravelTrip::query()->where('company_id', $company->id)->where('external_id', 'T1')->count());
        $this->assertSame(1, TravelBooking::query()->where('company_id', $company->id)->where('idempotency_key', 'legacy:B1')->count());

        // Mapping : statut figé + minor units.
        $booking = TravelBooking::query()->where('idempotency_key', 'legacy:B1')->firstOrFail();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame(15000, $booking->total_amount_minor);
    }

    public function test_dry_run_writes_nothing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->seedCities($company);

        $report = app(TenantManager::class)->withinTenant($company, fn () => app(LegacyTravelImportService::class)->import($company->id, $this->dump(), dryRun: true));

        $this->assertSame(1, $report['bookings']);
        $this->assertSame(0, TravelBooking::query()->count());
        $this->assertSame(0, TravelCarrier::query()->count());
    }

    public function test_skipped_items_are_reported(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Sans villes seedées → les routes/trajets/réservations sont sautés.
        $report = app(TenantManager::class)->withinTenant($company, fn () => app(LegacyTravelImportService::class)->import($company->id, $this->dump()));

        $this->assertSame(1, $report['carriers']);
        $this->assertSame(0, $report['routes']);
        $this->assertNotEmpty($report['skipped']);
    }
}
