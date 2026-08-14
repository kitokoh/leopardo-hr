<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationAuditor;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1874 — audit & observabilité de chaque calcul de paie.
 *
 * - chaque simulation expose un identifiant de corrélation et écrit une
 *   ligne d'audit (contexte pays, version des règles, entrées non
 *   sensibles, résultats agrégés, acteur) ;
 * - chaque run de paie écrit une ligne d'audit ;
 * - l'endpoint GET /payroll/calculations respecte l'isolation tenant et le
 *   RBAC (manager = sa société ; super-admin = tout) ;
 * - la table ne peut pas contenir de secrets (colonnes whitelistées).
 */
class PayrollCalculationAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'CI'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'XOF']);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        return $manager;
    }

    private function superAdmin(): SuperAdmin
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);
        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    public function test_simulation_exposes_correlation_id_and_writes_audit(): void
    {
        $company = $this->company();
        $this->manager($company);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 100000,
            'country_code' => 'CI',
        ])->assertOk();

        $correlationId = $response->json('data.correlation_id');
        $this->assertNotEmpty($correlationId);

        /** @var PayrollCalculationAudit $audit */
        $audit = PayrollCalculationAudit::query()->where('correlation_id', $correlationId)->firstOrFail();

        $this->assertSame((int) $company->id, (int) $audit->company_id);
        $this->assertSame('CI', $audit->country_code);
        $this->assertNotEmpty($audit->rules_version);
        $this->assertSame(100000.0, (float) $audit->input_gross);
        // Net et impôt dépendent du barème pays en vigueur (CI pilot) — on
        // verrouille la COHÉRENCE (net < brut, impôt ≥ 0) plutôt que des
        // valeurs absolues (celles-ci sont couvertes par les golden pays).
        $this->assertLessThan(100000.0, (float) $audit->result_net);
        $this->assertGreaterThanOrEqual(0.0, (float) $audit->result_income_tax);
        $this->assertSame('ok', $audit->status);
        $this->assertNotNull($audit->actor_id);
    }

    public function test_calculate_run_writes_audit(): void
    {
        $company = $this->company();
        $this->manager($company);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 400000,
            'contract_start' => '2026-01-01',
            'contract_end' => '2026-12-31',
        ]);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille CI',
            'base_salary' => 400000,
            'currency' => 'XOF',
            'country_code' => 'CI',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'CI',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        (new PayrollCalculator)->calculateRun($run);

        $audit = PayrollCalculationAudit::query()
            ->where('country_code', 'CI')
            ->where('actor_role', 'system')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((int) $company->id, (int) $audit->company_id);
        $this->assertSame(400000.0, (float) $audit->input_gross);
        $this->assertGreaterThan(0.0, (float) $audit->result_net);
        $this->assertNotEmpty($audit->correlation_id);
    }

    public function test_audit_endpoint_respects_tenant_isolation(): void
    {
        $companyA = $this->company();
        $companyB = $this->company('SN');
        $this->manager($companyA);

        // Deux calculs : un sur A (manager courant), un sur B (autre tenant).
        $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 100000,
            'country_code' => 'CI',
        ])->assertOk();

        $this->manager($companyB);
        $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 100000,
            'country_code' => 'SN',
        ])->assertOk();

        // Retour sur A : seuls les calculs de A sont visibles.
        $this->manager($companyA);
        $list = $this->getJson('/api/v1/payroll/calculations')->assertOk()->json('data.data');

        $this->assertNotEmpty($list);
        foreach ($list as $audit) {
            $this->assertSame((int) $companyA->id, (int) $audit['company_id']);
        }
    }

    public function test_audit_endpoint_rbac_employee_forbidden(): void
    {
        $company = $this->company();
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/payroll/calculations')->assertForbidden();
    }

    public function test_audit_endpoint_super_admin_sees_all_with_company_filter(): void
    {
        $company = $this->company();
        $this->manager($company);
        $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 100000,
            'country_code' => 'CI',
        ])->assertOk();

        $this->superAdmin();

        $all = $this->getJson('/api/v1/payroll/calculations')->assertOk()->json('data.data');
        $this->assertNotEmpty($all);

        $filtered = $this->getJson('/api/v1/payroll/calculations?company_id='.$company->id)
            ->assertOk()->json('data.data');
        $this->assertNotEmpty($filtered);
        foreach ($filtered as $audit) {
            $this->assertSame((int) $company->id, (int) $audit['company_id']);
        }
    }

    public function test_audit_table_has_no_sensitive_columns(): void
    {
        $columns = Schema::getColumnListing('payroll_calculation_audits');

        foreach ($columns as $column) {
            $this->assertStringNotContainsStringIgnoringCase('password', $column);
            $this->assertStringNotContainsStringIgnoringCase('token', $column);
            $this->assertStringNotContainsStringIgnoringCase('biometric', $column);
            $this->assertStringNotContainsStringIgnoringCase('secret', $column);
        }
    }

    public function test_auditor_never_persists_sensitive_payload_keys(): void
    {
        // Défense en profondeur : même si un appelant passait des clés
        // sensibles, l'auditeur ne les persistrait pas (whitelist stricte).
        $audit = (new PayrollCalculationAuditor)->record([
            'company_id' => 1,
            'country_code' => 'CI',
            'correlation_id' => '00000000-0000-4000-8000-000000000000',
            'input_gross' => 100000,
            'password' => 'supersecret',
            'biometric_data' => 'raw-biometric',
        ]);

        $this->assertNull($audit->getAttribute('password'));
        $this->assertNull($audit->getAttribute('biometric_data'));
    }
}
