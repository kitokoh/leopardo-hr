<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-018 (#5812) — Import CSV contrôlé (preview + transactionnel + audit).
 *
 * Couvre la validation ligne par ligne, les limites, le rollback logique et
 * la trace `fuel_imports`.
 */
class FuelImportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
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

    public function test_preview_reports_line_errors_without_side_effect(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        $this->postJson('/api/v1/fuel-station/imports/preview', [
            'type' => 'products',
            'rows' => [
                ['code' => 'ESS', 'name' => 'Essence', 'unit_code' => 'l'],
                ['code' => '', 'name' => '', 'unit_code' => 'xx'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.total', 2);

        // Aucun effet de bord en preview.
        $this->assertSame(0, FuelProduct::query()->count());
    }

    public function test_import_is_transactional_with_rollback(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        // 1 ligne valide + 1 invalide → rien n'est inséré (rollback logique).
        $this->postJson('/api/v1/fuel-station/imports', [
            'type' => 'products',
            'rows' => [
                ['code' => 'ESS', 'name' => 'Essence', 'unit_code' => 'l'],
                ['code' => 'X', 'name' => '', 'unit_code' => 'u'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, FuelProduct::query()->count());
        $this->assertSame(0, FuelImport::query()->count());
    }

    public function test_valid_import_creates_audit_trail(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        $this->postJson('/api/v1/fuel-station/imports', [
            'type' => 'products',
            'rows' => [
                ['code' => 'ESS', 'name' => 'Essence', 'unit_code' => 'l'],
                ['code' => 'GAZ', 'name' => 'Gazole', 'unit_code' => 'l'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.type', 'products');

        $this->assertSame(2, FuelProduct::query()->count());
        $this->assertSame(1, FuelImport::query()->count());
    }

    public function test_import_rejects_too_many_rows(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->manager($company);

        $rows = array_fill(0, 5001, ['code' => 'A', 'name' => 'B', 'unit_code' => 'u']);

        $this->postJson('/api/v1/fuel-station/imports/preview', ['type' => 'products', 'rows' => $rows])
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_operator_cannot_import(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/imports', [
            'type' => 'products',
            'rows' => [['code' => 'A', 'name' => 'B', 'unit_code' => 'u']],
        ])->assertStatus(403);
    }
}
