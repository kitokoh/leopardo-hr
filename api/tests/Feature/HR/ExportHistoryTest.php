<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\ExportHistory;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2199 — GET /export/history était un stub (`data => []` en dur).
 *
 * Chaque export du portail manager doit être historisé (append-only,
 * tenant-scopé) et /export/history doit répondre sur cette source réelle,
 * paginée, avec isolation tenant stricte.
 */
class ExportHistoryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_export_records_history(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/export/employees?format=csv')->assertOk();
        $this->getJson('/api/v1/export/vehicles')->assertOk();

        // Deux lignes d'historique, tenant-scopées, avec les bons champs.
        $this->assertDatabaseHas('export_history', [
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'type' => 'employees',
            'format' => 'csv',
        ]);
        $this->assertDatabaseHas('export_history', [
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'type' => 'vehicles',
            'format' => 'json',
        ]);

        $response = $this->getJson('/api/v1/export/history')->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.type', 'vehicles'); // ordre décroissant
    }

    public function test_history_is_tenant_scoped(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();

        /** @var Company $companyB */
        $companyB = Company::factory()->create();

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);

        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerA);
        $this->getJson('/api/v1/export/employees')->assertOk();

        // Le manager B ne voit PAS l'historique du tenant A.
        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/export/history')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(1, ExportHistory::where('company_id', $companyA->id)->count());
        $this->assertSame(0, ExportHistory::where('company_id', $companyB->id)->count());
    }

    public function test_history_supports_type_filter_and_pagination(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/export/employees')->assertOk();
        $this->getJson('/api/v1/export/attendance')->assertOk();
        $this->getJson('/api/v1/export/pay-slips')->assertOk();

        // Filtre par type.
        $this->getJson('/api/v1/export/history?type=employees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'employees');

        // Pagination (per_page=2 sur 3 lignes).
        $page = $this->getJson('/api/v1/export/history?per_page=2')->assertOk();
        $page->assertJsonCount(2, 'data');
        $page->assertJsonPath('meta.total', 3);
        $page->assertJsonPath('meta.last_page', 2);
    }

    public function test_export_history_requires_manager_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/export/history')->assertForbidden();
    }
}
