<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2267 — contrat OpenAPI GET/POST /bank-exports : la spec documentait
 * ces routes sans implémentation (clients générés → 404). Implémentation
 * réelle : liste paginée tenant-scope + génération (équivalent du POST
 * /payroll-runs/{run}/bank-export).
 */
class BankExportContractApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_index_lists_tenant_bank_exports(): void
    {
        [$company, $manager] = $this->context();

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'validated',
        ]);

        BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'csv_generic',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/v1/bank-exports');
        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'format', 'status', 'payroll_run']]])
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_manager_role(): void
    {
        [$company, $employee] = $this->context(manager: false);

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/bank-exports')->assertForbidden();
    }

    public function test_index_isolated_between_tenants(): void
    {
        [$companyA, $managerA] = $this->context();
        [, $managerB] = $this->context();

        $run = PayrollRun::query()->create([
            'company_id' => $companyA->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'validated',
        ]);
        BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $companyA->id,
            'format' => 'csv_generic',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/bank-exports')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_store_creates_pending_export_and_dispatches_job(): void
    {
        Queue::fake();

        [$company, $manager] = $this->context();

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'validated',
        ]);

        Sanctum::actingAs($manager);
        $response = $this->postJson('/api/v1/bank-exports', [
            'payroll_run_id' => $run->id,
            'format' => 'sepa_xml',
        ]);
        $response->assertStatus(202)
            ->assertJsonPath('data.status', BankExport::STATUS_PENDING);

        Queue::assertPushed(\App\Jobs\GenerateBankExportJob::class);
    }

    public function test_store_rejects_unvalidated_run(): void
    {
        [$company, $manager] = $this->context();

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/bank-exports', [
            'payroll_run_id' => $run->id,
            'format' => 'csv_generic',
        ])->assertStatus(422);
    }

    public function test_store_unknown_run_returns_422(): void
    {
        [$company, $manager] = $this->context();

        Sanctum::actingAs($manager);
        // Le contrôleur masque l'existence du run (anti-fuite) : réponse 422
        // « Payroll run not found » (cf. BankExportController@store — le run
        // inconnu OU cross-tenant renvoie la même erreur de validation).
        $this->postJson('/api/v1/bank-exports', [
            'payroll_run_id' => 999_999,
            'format' => 'csv_generic',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Run de paie introuvable.');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function context(bool $manager = true): array
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();

        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = $manager
            ? Employee::factory()->manager()->create(['company_id' => $company->id])
            : Employee::factory()->create(['company_id' => $company->id]);

        return [$company, $employee];
    }
}
