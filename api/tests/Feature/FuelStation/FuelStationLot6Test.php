<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Lot 6 (audit 2026-10-17) - P1-c (convention monetaire), P1-e (logo de
 * station) et P1-f (vue comparative inter-stations).
 *
 * P1-c : la convention documentee du module (entiers en unites mineures) est
 * exposee de facon ADDITIVE (`amount_minor`, `unit_price_minor`,
 * `quantity_minor`, `currency`) sans casser les champs historiques.
 * P1-e : logo de station via le stockage du branding societe, isole par tenant.
 * P1-f : comparaison inter-stations, tenant-scoped, stations muettes incluses.
 */
class FuelStationLot6Test extends TestCase
{
    use RefreshTenantDatabase;

    private function makeStation(Company $company, string $code, string $name): FuelStation
    {
        return FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'status' => 'active',
        ]);
    }

    private function makeSale(Company $company, Employee $employee, FuelStation $station, float $qty, float $price): FuelSale
    {
        return FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $employee->id,
            'product' => 'diesel',
            'quantity' => $qty,
            'unit_price' => $price,
            'amount' => round($qty * $price, 2),
            'currency' => 'DZD',
            'payment_method' => FuelSale::PAYMENT_CASH,
            'sale_time' => now(),
            'source' => FuelSale::SOURCE_MANUAL,
        ]);
    }

    public function test_sale_payload_exposes_minor_units_without_breaking_legacy_fields(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);
        $station = $this->makeStation($company, 'ST-1', 'Station 1');

        $sale = $this->makeSale($company, $manager, $station, 12.5, 45.9);

        $response = $this->actingAs($manager)->getJson("/api/v1/fuel-station/sales/{$sale->id}");

        $response->assertOk();

        // Convention cible : entiers en unites mineures.
        $response->assertJsonPath('data.amount_minor', 57375);
        $response->assertJsonPath('data.unit_price_minor', 4590);
        $response->assertJsonPath('data.quantity_minor', 12500);
        $response->assertJsonPath('data.currency', 'DZD');

        // Champs historiques conserves (app mobile gelee) : aucun retrait.
        $response->assertJsonPath('data.amount', 573.75);
        $response->assertJsonPath('data.unit_price', 45.9);
        $response->assertJsonPath('data.quantity', 12.5);
    }

    public function test_minor_unit_accessors_round_to_the_nearest_cent(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);
        $station = $this->makeStation($company, 'ST-1', 'Station 1');

        // 3 x 33.335 = 100.005 -> arrondi au centime le plus proche.
        $sale = $this->makeSale($company, $manager, $station, 3.0, 33.335);

        self::assertSame(10001, $sale->amountMinor());
        self::assertSame(3334, $sale->unitPriceMinor());
        self::assertSame(3000, $sale->quantityMinor());
    }

    public function test_station_logo_upload_stores_under_tenant_prefix_and_is_returned(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);
        $station = $this->makeStation($company, 'ST-1', 'Station 1');

        $response = $this->actingAs($manager)->postJson(
            "/api/v1/fuel-station/stations/{$station->id}/logo",
            ['logo' => UploadedFile::fake()->image('logo.png', 120, 120)]
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $station->id);

        $station->refresh();

        self::assertNotNull($station->logo_path);
        self::assertSame('public', $station->logo_disk);
        self::assertStringStartsWith("fuel-station-logos/{$company->id}/", (string) $station->logo_path);
        Storage::disk('public')->assertExists((string) $station->logo_path);
    }

    public function test_station_logo_upload_rejects_non_image(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);
        $station = $this->makeStation($company, 'ST-1', 'Station 1');

        $response = $this->actingAs($manager)->postJson(
            "/api/v1/fuel-station/stations/{$station->id}/logo",
            ['logo' => UploadedFile::fake()->create('payload.pdf', 10, 'application/pdf')]
        );

        $response->assertStatus(422);
        self::assertNull($station->refresh()->logo_path);
    }

    public function test_station_logo_is_isolated_per_tenant(): void
    {
        Storage::fake('public');

        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'features' => ['fuel_station' => true]]);

        $managerB = Employee::factory()->for($companyB)->create(['role' => 'manager']);
        $stationA = $this->makeStation($companyA, 'ST-A', 'Station A');

        // Le manager de B ne voit meme pas la station de A : 404, pas 403.
        $this->actingAs($managerB)->postJson(
            "/api/v1/fuel-station/stations/{$stationA->id}/logo",
            ['logo' => UploadedFile::fake()->image('logo.png', 60, 60)]
        )->assertStatus(404);

        self::assertNull($stationA->refresh()->logo_path);
    }

    public function test_station_logo_delete_removes_file_and_columns(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);
        $station = $this->makeStation($company, 'ST-1', 'Station 1');

        $this->actingAs($manager)->postJson(
            "/api/v1/fuel-station/stations/{$station->id}/logo",
            ['logo' => UploadedFile::fake()->image('logo.png', 60, 60)]
        )->assertOk();

        $path = (string) $station->refresh()->logo_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($manager)
            ->deleteJson("/api/v1/fuel-station/stations/{$station->id}/logo")
            ->assertOk();

        $station->refresh();

        self::assertNull($station->logo_path);
        self::assertNull($station->logo_url);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_station_comparison_aggregates_each_station_and_includes_silent_ones(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);

        $stationA = $this->makeStation($company, 'ST-A', 'Alpha');
        $stationB = $this->makeStation($company, 'ST-B', 'Bravo');

        $this->makeSale($company, $manager, $stationA, 10.0, 50.0); // 500.00
        $this->makeSale($company, $manager, $stationA, 5.0, 50.0);  // 250.00
        $this->makeSale($company, $manager, $stationB, 2.0, 100.0); // 200.00

        $response = $this->actingAs($manager)->getJson(
            '/api/v1/fuel-station/reports/station-comparison?period_start='.now()->startOfMonth()->toDateString()
            .'&period_end='.now()->toDateString()
        );

        $response->assertOk();
        $response->assertJsonPath('data.totals.station_count', 2);
        $response->assertJsonPath('data.totals.sale_count', 3);
        $response->assertJsonPath('data.totals.amount_total', 950);
        $response->assertJsonPath('data.totals.amount_total_minor', 95000);

        $stations = $response->json('data.stations');
        self::assertIsArray($stations);
        self::assertCount(2, $stations);

        // Tri par nom : Alpha avant Bravo.
        self::assertSame('Alpha', $stations[0]['station_name']);
        self::assertSame(2, $stations[0]['sale_count']);
        self::assertSame(750, $stations[0]['amount_total']);
        self::assertSame(75000, $stations[0]['amount_total_minor']);

        self::assertSame('Bravo', $stations[1]['station_name']);
        self::assertSame(1, $stations[1]['sale_count']);
        self::assertSame(200, $stations[1]['amount_total']);
    }

    public function test_station_comparison_includes_station_without_activity(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->for($company)->create(['role' => 'manager']);

        $stationA = $this->makeStation($company, 'ST-A', 'Alpha');
        $this->makeStation($company, 'ST-B', 'Bravo'); // aucune vente

        $this->makeSale($company, $manager, $stationA, 1.0, 10.0);

        $response = $this->actingAs($manager)->getJson(
            '/api/v1/fuel-station/reports/station-comparison?period_start='.now()->startOfMonth()->toDateString()
            .'&period_end='.now()->toDateString()
        );

        $response->assertOk();
        $response->assertJsonPath('data.totals.station_count', 2);

        $stations = $response->json('data.stations');
        self::assertSame(0, $stations[1]['sale_count']);
        self::assertSame(0, $stations[1]['amount_total']);
    }

    public function test_station_comparison_is_isolated_per_tenant(): void
    {
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'features' => ['fuel_station' => true]]);

        $managerA = Employee::factory()->for($companyA)->create(['role' => 'manager']);
        $managerB = Employee::factory()->for($companyB)->create(['role' => 'manager']);

        $stationA = $this->makeStation($companyA, 'ST-A', 'Alpha');
        $this->makeSale($companyA, $managerA, $stationA, 10.0, 50.0);

        $this->makeStation($companyB, 'ST-B', 'Bravo');

        $response = $this->actingAs($managerB)->getJson(
            '/api/v1/fuel-station/reports/station-comparison?period_start='.now()->startOfMonth()->toDateString()
            .'&period_end='.now()->toDateString()
        );

        $response->assertOk();
        $response->assertJsonPath('data.totals.station_count', 1);
        $response->assertJsonPath('data.totals.sale_count', 0);

        $stations = $response->json('data.stations');
        self::assertSame('Bravo', $stations[0]['station_name']);
    }

    public function test_station_comparison_requires_manager(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => ['fuel_station' => true]]);
        $operator = Employee::factory()->for($company)->create(['role' => 'employee']);

        $this->makeStation($company, 'ST-A', 'Alpha');

        $this->actingAs($operator)->getJson(
            '/api/v1/fuel-station/reports/station-comparison?period_start='.now()->startOfMonth()->toDateString()
            .'&period_end='.now()->toDateString()
        )->assertStatus(403);
    }
}
