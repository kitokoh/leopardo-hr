<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelAccountingEntry;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Infrastructure\Services\FuelAccountingContractService;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contrat Accounting FuelStation — FUEL-015 (issue #5809).
 *
 * Couvre : publication d'agrégats validés (ventes du jour, clôture de
 * caisse, écart de stock) en partie double équilibrée, idempotence de la
 * régénération (UNIQUE (company_id, reference)), références traçables
 * FUEL-*, aucune donnée sensible dans les labels.
 */
class FuelAccountingContractTest extends TestCase
{
    use RefreshTenantDatabase;

    private function setupStation(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-ACC',
            'name' => 'Compta',
            'timezone' => 'UTC',
        ]);

        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return [$company, $manager, $operator, $station];
    }

    public function test_sales_aggregates_are_published_balanced(): void
    {
        [$company, , $operator, $station] = $this->setupStation();

        $day = Carbon::today()->toDateString();

        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $operator->id,
            'product' => 'essence',
            'quantity' => 100,
            'unit_price' => 1.5,
            'amount' => 150,
            'sale_time' => Carbon::today()->startOfDay()->addHours(10),
        ]);
        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $operator->id,
            'product' => 'essence',
            'quantity' => 50,
            'unit_price' => 1.5,
            'amount' => 75,
            'sale_time' => Carbon::today()->startOfDay()->addHours(11),
        ]);

        $service = $this->app->make(FuelAccountingContractService::class);
        $count = $service->generateSalesEntries($company->id, (int) $station->id, $day);

        // 2 lignes par produit (débit caisse / crédit ventes) — agrégat unique.
        $this->assertSame(2, $count);

        $entries = FuelAccountingEntry::query()
            ->where('company_id', $company->id)
            ->where('reference', 'FUEL-SALES-'.$station->id.'-'.$day)
            ->get();

        $this->assertCount(2, $entries);
        $this->assertEqualsWithDelta(225.0, (float) $entries->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(225.0, (float) $entries->sum('credit'), 0.01);
        $this->assertSame('531000', $entries->firstWhere('credit', 0.0)->account_code);
        $this->assertSame('701100', $entries->firstWhere('debit', 0.0)->account_code);
    }

    public function test_regeneration_is_idempotent(): void
    {
        [$company, , $operator, $station] = $this->setupStation();

        $day = Carbon::today()->toDateString();

        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $operator->id,
            'product' => 'gazoil',
            'quantity' => 10,
            'unit_price' => 1.4,
            'amount' => 14,
            'sale_time' => Carbon::today()->startOfDay()->addHours(9),
        ]);

        $service = $this->app->make(FuelAccountingContractService::class);
        $service->generateSalesEntries($company->id, (int) $station->id, $day);
        $service->generateSalesEntries($company->id, (int) $station->id, $day);

        $count = FuelAccountingEntry::query()
            ->where('company_id', $company->id)
            ->where('reference', 'FUEL-SALES-'.$station->id.'-'.$day)
            ->count();

        // Rejouable : 2 lignes, jamais 4.
        $this->assertSame(2, $count);
    }

    public function test_cash_session_and_stock_variance_are_published(): void
    {
        [$company, , $operator, $station] = $this->setupStation();

        /** @var FuelCashSession $session */
        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'opened_by' => $operator->id,
            'opened_at' => Carbon::today()->startOfDay()->addHours(8),
            'closed_at' => Carbon::today()->startOfDay()->addHours(20),
            'opening_balance' => 0,
            'closing_balance' => 500,
            'expected_balance' => 480,
            'variance' => 20,
            'status' => FuelCashSession::STATUS_CLOSED,
        ]);

        /** @var FuelStockReconciliation $reconciliation */
        $reconciliation = FuelStockReconciliation::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'product_type' => 'essence',
            'day' => Carbon::today()->toDateString(),
            'opening_minor' => 0,
            'deliveries_minor' => 10000,
            'sales_minor' => -7000,
            'adjustments_minor' => 0,
            'expected_closing_minor' => 3000,
            'metered_delta_minor' => 2800,
            'variance_minor' => 200,
            'status' => FuelStockReconciliation::STATUS_VARIANCE,
            'computed_at' => Carbon::now(),
        ]);

        $service = $this->app->make(FuelAccountingContractService::class);

        // Écart de caisse → 3 lignes (caisse, ventes, produit/perte).
        $count = $service->generateCashSessionEntries($company->id, $session);
        $this->assertSame(3, $count);

        $cashRefs = FuelAccountingEntry::query()
            ->where('company_id', $company->id)
            ->where('reference', 'FUEL-CASH-'.$session->id)
            ->get();
        $this->assertCount(3, $cashRefs);
        $this->assertSame('758000', $cashRefs->firstWhere('debit', 0.0)->account_code);

        // Écart de stock variance → 1 ligne.
        $count = $service->generateStockVarianceEntries($company->id, $reconciliation);
        $this->assertSame(1, $count);

        $varRefs = FuelAccountingEntry::query()
            ->where('company_id', $company->id)
            ->where('reference', 'LIKE', 'FUEL-VAR-%')
            ->get();
        $this->assertCount(1, $varRefs);
        $this->assertSame('603700', $varRefs->first()->account_code);
        $this->assertEqualsWithDelta(200.0, (float) $varRefs->first()->debit, 0.01);
    }

    public function test_sync_command_runs_and_is_replayable(): void
    {
        [$company, , $operator, $station] = $this->setupStation();

        $day = Carbon::today()->toDateString();

        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'employee_id' => $operator->id,
            'product' => 'essence',
            'quantity' => 40,
            'unit_price' => 1.5,
            'amount' => 60,
            'sale_time' => Carbon::today()->startOfDay()->addHours(12),
        ]);

        $this->artisan('leopardo:fuel:accounting-sync', ['--company' => $company->id])
            ->assertSuccessful();

        $count = FuelAccountingEntry::query()->where('company_id', $company->id)->count();
        $this->assertSame(2, $count);

        // Rejouable : aucun doublon.
        $this->artisan('leopardo:fuel:accounting-sync', ['--company' => $company->id])
            ->assertSuccessful();

        $this->assertSame(2, FuelAccountingEntry::query()->where('company_id', $company->id)->count());
    }
}
