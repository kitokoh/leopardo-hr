<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Sécurité, performance et observabilité FuelStation — FUEL-020 (issue #5814).
 *
 * Verrouille :
 *   1. la commande de rapprochement est sûre sur un tenant SANS la solution
 *      (no-op, exit SUCCESS — pas d'exception sur les tenants non activés) ;
 *   2. la commande est rejouable sur un tenant actif (2 exécutions → même
 *      nombre de snapshots, upsert idempotent) ;
 *   3. la surface `fuel_station` est enregistrée dans le registre des threat
 *      models (MAT-017, garde CI) — traçabilité sécurité du périmètre.
 */
class FuelHardeningTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_reconcile_command_is_noop_for_company_without_solution(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        $this->artisan('leopardo:fuel:reconcile-stock', ['--company' => $company->id])
            ->assertSuccessful();
    }

    public function test_reconcile_command_is_replayable_on_active_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-REC',
            'name' => 'Reco',
            'timezone' => 'UTC',
        ]);

        $this->artisan('leopardo:fuel:reconcile-stock', ['--company' => $company->id])
            ->assertSuccessful();

        $countAfterFirstRun = $this->snapshotCount($company->id, (int) $station->id);

        $this->artisan('leopardo:fuel:reconcile-stock', ['--company' => $company->id])
            ->assertSuccessful();

        // Rejouable : aucun doublon de snapshots.
        $this->assertSame($countAfterFirstRun, $this->snapshotCount($company->id, (int) $station->id));
    }

    public function test_fuel_station_surface_is_registered_in_threat_models(): void
    {
        $registry = json_decode(
            (string) file_get_contents(base_path('dev-hub/tools/security-threat-models.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $ids = array_column($registry['surfaces'], 'id');

        $this->assertContains('fuel_station', $ids);

        $surface = $registry['surfaces'][array_search('fuel_station', $ids, true)];

        $this->assertArrayHasKey('doc', $surface);
        $this->assertFileExists(base_path($surface['doc']));
        $this->assertContains('audit', $surface['controls']);
    }

    private function snapshotCount(string $companyId, int $stationId): int
    {
        return (int) \App\Modules\FuelStation\Domain\Models\FuelStockReconciliation::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->count();
    }
}
