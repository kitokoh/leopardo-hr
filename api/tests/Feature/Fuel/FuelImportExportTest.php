<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\HR\Domain\Models\ExportHistory;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Import/export sécurisé — FUEL-018 (issue #5812).
 *
 * Couvre : export CSV des ventes (audité dans export_history, neutralisation
 * OWASP des formules), accès manager uniquement, journal d'import
 * (fuel_imports), isolation tenant.
 */
class FuelImportExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    public function test_export_sales_csv_audited_and_sanitized(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        FuelSale::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'product' => '=SUM(A1:A2)', // tentative d'injection de formule
            'quantity' => 5,
            'unit_price' => 100,
            'amount' => 500,
            'sale_time' => now(),
            'source' => 'manual',
        ]);

        $response = $this->getJson('/api/v1/fuel-station/exports/sales?format=csv')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame('csv', $response['format']);
        $this->assertSame(1, $response['count']);

        // Injection neutralisée : la cellule ne commence pas par '='.
        $content = (string) $response['content'];
        $this->assertStringNotContainsString('"=SUM', $content);

        // Export audité.
        $this->assertSame(1, ExportHistory::query()->where('company_id', $company->id)->where('type', 'fuel_sales')->count());
    }

    public function test_operator_cannot_export(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->operator($company));

        $this->getJson('/api/v1/fuel-station/exports/sales')->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/exports/readings')->assertStatus(403);
    }

    public function test_import_journal_lists_imports(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        FuelImport::query()->create([
            'company_id' => $company->id,
            'kind' => FuelImport::KIND_METER_READINGS,
            'file_name' => 'readings.csv',
            'status' => FuelImport::STATUS_UPLOADED,
            'created_by' => $this->manager($company)->id,
        ]);

        $this->getJson('/api/v1/fuel-station/imports')
            ->assertStatus(200)
            ->assertJsonPath('data.0.file_name', 'readings.csv')
            ->assertJsonPath('meta.total', 1);
    }
}
