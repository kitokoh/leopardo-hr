<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Application\Jobs\FuelReconciliationJob;
use App\Modules\FuelStation\Domain\Enums\FuelStockMovementType;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stocks, cuves et rapprochement FuelStation — FUEL-009 (issue #5803).
 *
 * Critères : rapport d'écart EXPLICABLE ; aucun ajustement silencieux ;
 * jobs de rapprochement rejouables (sans doublon).
 */
class FuelStockReconciliationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function station(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-01',
            'name' => 'Station Test',
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        return $station;
    }

    private function tank(Company $company, FuelStation $station): FuelTank
    {
        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'TK-01',
            'product_type' => 'ESS95',
            'capacity_minor' => 10000,
            'current_level_minor' => 8000,
            'status' => 'active',
        ]);

        return $tank;
    }

    public function test_movements_are_idempotent_by_key(): void
    {
        $company = $this->company();
        $tank = $this->tank($company, $this->station($company));

        $service = new FuelStockService;

        $first = $service->recordDelivery($tank, 5000.0, ['reference' => 'BL-2026-001']);
        $second = $service->recordDelivery($tank, 5000.0, ['reference' => 'BL-2026-001']);

        self::assertSame($first->id, $second->id);
        self::assertSame(1, FuelStockMovement::query()->where('company_id', $company->id)->count());
    }

    public function test_reconciliation_explains_variance_without_silent_adjustment(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $tank = $this->tank($company, $station);

        $service = new FuelStockService;
        $service->recordMovement($tank, FuelStockMovementType::Opening, 8000.0, ['reference' => 'opening-2026-08']);
        $service->recordDelivery($tank, 5000.0, ['reference' => 'BL-2026-08-001']);
        $service->recordSale($tank, 12000.0, ['reference' => 'sales-2026-08']);
        // Comptage physique : 1 200 l attendus, 1 150 l comptés → écart −50 l.
        $service->recordClosingCount($tank, 1150.0, ['reference' => 'count-2026-08-31']);

        $report = $service->reconcile($company->id, $station->id, '2026-08');

        self::assertSame(FuelStockReconciliation::STATUS_VARIANCE, $report->status);
        self::assertSame(-50.0, $report->variance_liters);
        self::assertSame(1200.0, $report->expected_level);

        $tankRow = collect($report->data['tanks'])->firstWhere('tank_id', $tank->id);
        self::assertSame(8000.0, $tankRow['opening']);
        self::assertSame(5000.0, $tankRow['delivered']);
        self::assertSame(12000.0, $tankRow['sold']);
        self::assertSame(1200.0, $tankRow['expected_level']);
        self::assertSame(1150.0, $tankRow['actual_level']);
        self::assertSame(-50.0, $tankRow['variance_liters']);
        self::assertSame(FuelStockReconciliation::STATUS_VARIANCE, $tankRow['status']);

        // Aucun ajustement silencieux : le grand-livre n'a pas été muté.
        self::assertSame(4, FuelStockMovement::query()->where('company_id', $company->id)->count());
        self::assertSame(12000.0, FuelStockMovement::query()
            ->where('tank_id', $tank->id)
            ->where('type', FuelStockMovementType::Sale->value)
            ->value('quantity'));
    }

    public function test_reconciliation_is_ok_within_tolerance(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $tank = $this->tank($company, $station);

        $service = new FuelStockService;
        $service->recordMovement($tank, FuelStockMovementType::Opening, 8000.0, ['reference' => 'opening']);
        $service->recordSale($tank, 3000.0, ['reference' => 'sales']);
        $service->recordClosingCount($tank, 4999.8, ['reference' => 'count']); // écart −0,2 l ≤ tolérance

        $report = $service->reconcile($company->id, $station->id, '2026-08');

        self::assertSame(FuelStockReconciliation::STATUS_OK, $report->status);
    }

    public function test_reconciliation_requires_closing_count_for_status(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $tank = $this->tank($company, $station);

        $service = new FuelStockService;
        $service->recordDelivery($tank, 5000.0, ['reference' => 'BL']);

        $report = $service->reconcile($company->id, $station->id, '2026-08');

        self::assertSame(FuelStockReconciliation::STATUS_INSUFFICIENT_DATA, $report->status);
        self::assertNull(collect($report->data['tanks'])->firstWhere('tank_id', $tank->id)['actual_level']);
    }

    public function test_reconciliation_job_is_replayable_without_duplicates(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $tank = $this->tank($company, $station);

        $service = new FuelStockService;
        $service->recordDelivery($tank, 5000.0, ['reference' => 'BL']);
        $service->recordClosingCount($tank, 4999.9, ['reference' => 'count']);

        (new FuelReconciliationJob($company->id, $station->id, '2026-08'))->handle($service);
        (new FuelReconciliationJob($company->id, $station->id, '2026-08'))->handle($service);

        // Rejeu = remplacement, jamais de doublon.
        self::assertSame(1, FuelStockReconciliation::query()
            ->where('company_id', $company->id)
            ->where('station_id', $station->id)
            ->where('period', '2026-08')
            ->count());

        Queue::fake();
        FuelReconciliationJob::dispatch($company->id, $station->id, '2026-08');
        Queue::assertPushed(FuelReconciliationJob::class, fn (FuelReconciliationJob $job): bool => $job->period() === '2026-08');
    }

    public function test_reconciliation_is_tenant_isolated(): void
    {
        $companyA = $this->company();
        $stationA = $this->station($companyA);
        $tankA = $this->tank($companyA, $stationA);

        $companyB = $this->company();
        $stationB = $this->station($companyB);
        $this->tank($companyB, $stationB);

        $service = new FuelStockService;
        $service->recordDelivery($tankA, 9999.0, ['reference' => 'BL-A']);

        $reportB = $service->reconcile($companyB->id, $stationB->id, '2026-08');

        // La cuve de A n'apparaît pas dans le rapport de B.
        self::assertNotContains($tankA->id, collect($reportB->data['tanks'] ?? [])->pluck('tank_id')->all());
        self::assertSame(0.0, $reportB->delivered_quantity);
    }

    public function test_api_movements_and_reports_require_manager(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $tank = $this->tank($company, $station);

        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        // Opérateur ≠ manager → 403 sur l'écriture de mouvement et les rapports.
        $this->postJson("/api/v1/fuel-station/stock/movements/{$tank->id}", [
            'type' => 'delivery',
            'quantity' => 100,
        ])->assertForbidden();

        $this->postJson("/api/v1/fuel-station/stock/reconcile/{$station->id}", [
            'period' => '2026-08',
        ])->assertForbidden();

        $this->getJson('/api/v1/fuel-station/stock/reports')->assertForbidden();

        // Manager → 201 + rapport.
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/stock/movements/{$tank->id}", [
            'type' => 'delivery',
            'quantity' => 5000,
            'reference' => 'BL-API-001',
        ])->assertCreated();

        $this->postJson("/api/v1/fuel-station/stock/reconcile/{$station->id}", [
            'period' => '2026-08',
        ])->assertOk()->assertJsonPath('data.status', FuelStockReconciliation::STATUS_INSUFFICIENT_DATA);

        $this->getJson('/api/v1/fuel-station/stock/reports')->assertOk();
    }
}
