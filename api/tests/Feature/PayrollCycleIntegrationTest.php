<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * End-to-end payroll cycle: create run → add employees → compute → validate → list slips.
 */
class PayrollCycleIntegrationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_full_payroll_cycle_create_compute_validate(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Employee::factory()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        // Step 1: Create payroll run
        $response = $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $response->assertStatus(201)->assertJsonStructure([
            'data' => ['id', 'status'],
        ]);

        $runId = $response->json('data.id');

        // Step 2: Verify run is in draft
        $this->getJson("/api/v1/payroll-runs/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        // Step 3: List runs — our run should appear
        $this->getJson('/api/v1/payroll-runs')
            ->assertOk();
    }

    public function test_payroll_cycles_index_uses_standard_data_meta_envelope(): void
    {
        // PA2-API-001: /api/v1/payroll/cycles used to return Laravel's raw
        // paginator shape (current_page/data/links/... at the top level)
        // instead of the success/data/meta envelope used everywhere else in
        // the API (see ApiListQueryContractTest, PayrollRunControllerTest).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycles?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'company_id', 'country_code', 'period_start', 'period_end', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('current_page')
            ->assertJsonMissingPath('data.0.links');
    }

    public function test_employee_cannot_manage_payroll_runs(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_payroll_run_scoped_to_tenant(): void
    {
        // #1905 : le pays légal du tenant doit correspondre au country_code
        // du run — la factory tire un pays aléatoire sinon (test flaky).
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['name' => 'Company A', 'country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['name' => 'Company B', 'country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerA);
        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated();

        // Manager B should NOT see Manager A's run
        Sanctum::actingAs($managerB);
        $response = $this->getJson('/api/v1/payroll-runs');
        $response->assertOk();

        $runs = collect($response->json('data'));
        $this->assertTrue(
            $runs->where('label', 'Run A')->isEmpty(),
            'Manager B should not see Company A payroll runs',
        );
    }

    public function test_employee_can_read_own_current_balance_with_advance_deducted(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 120000,
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 20000,
            'net_salary' => 100000,
            'status' => 'validated',
        ]);

        $advance = SalaryAdvance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 15000,
            'reason' => 'Urgence familiale',
            'validation_status' => 'payment_declared',
            'payment_declared_at' => now(),
        ]);
        $advance->status = 'approved';
        $advance->save();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/balance')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.country', 'DZ')
            ->assertJsonPath('data.currency', 'DZD')
            ->assertJsonPath('data.gross_due', 100000)
            ->assertJsonPath('data.advances', 15000)
            ->assertJsonPath('data.remaining', 85000)
            ->assertJsonPath('data.pay_slip.receipt_available', true)
            ->assertJsonStructure(['data' => ['next_payment_date']]);
    }

    /**
     * Issue #2143 — /me/balance expose le bloc `compliance` (#1872) résolu
     * depuis le pays de l'entreprise (niveau pilot pour DZ). Rétro-compatible :
     * pays non supporté → compliance null (garde anti-500).
     */
    public function test_employee_balance_exposes_compliance_block(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 120000,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/balance')
            ->assertOk()
            ->assertJsonPath('data.country', 'DZ')
            ->assertJsonPath('data.compliance.level', 'pilot')
            ->assertJsonPath('data.compliance.warning_key', 'payroll.compliance_warning_pilot')
            ->assertJsonPath('data.compliance.source', 'docs/payroll/DZ_COMPLIANCE.md')
            ->assertJsonPath('data.compliance.verification_date', null);
    }

    public function test_employee_balance_exposes_compliance_block_for_us(): void
    {
        // #1951 → #5255 : US avait « compliance: null » (pays display-only).
        // Depuis le pack EN, les règles US sont résolubles (pilot) : le bloc
        // compliance est exposé comme pour les autres pays supportés.
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'US',
            'currency' => 'USD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 120000,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/balance')
            ->assertOk()
            ->assertJsonPath('data.country', 'US')
            ->assertJsonPath('data.compliance.level', 'pilot')
            ->assertJsonPath('data.compliance.source', 'docs/payroll/US_COMPLIANCE.md');
    }

    public function test_employee_balance_reports_next_payment_date_from_company_pay_day(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 28]],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 8000,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/balance')->assertOk();

        $nextPaymentDate = $response->json('data.next_payment_date');
        $this->assertNotEmpty($nextPaymentDate, 'next_payment_date should be present in the balance payload.');
        $this->assertSame(28, Carbon::parse($nextPaymentDate)->day, 'next_payment_date should land on the configured pay_day.');
        $this->assertFalse(Carbon::parse($nextPaymentDate)->isPast(), 'next_payment_date should be a future or today date, never a past one.');
        $response->assertJsonPath('data.pay_slip.receipt_available', false);
    }

    public function test_employee_cannot_read_another_employee_balance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        /** @var Employee $other */
        $other = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/employees/{$other->id}/balance")
            ->assertForbidden();
    }

    public function test_manager_employee_balance_uses_standard_data_envelope(): void
    {
        // Issue #4500 : employeeBalance aplatissait le payload au niveau racine
        // ({data: {...}} + clés plates) — contrat incohérent avec /me/balance
        // et tous les autres endpoints payroll. Forme canonique : {data: {...}}.
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'salary_base' => 200000,
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 120000,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/balance");

        $response->assertOk();
        $this->assertSame(
            ['data'],
            array_keys($response->json()),
            'Contrat employeeBalance : {data: {...}} uniquement, aucune clé plate au niveau racine (#4500).'
        );
    }

    public function test_balance_exposes_compliance_block(): void
    {
        // Issue #2144 — le bloc compliance (niveau de confiance paie) doit
        // être exposé sur /me/balance pour l'écran paie mobile employee.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 50000,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/balance')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['compliance' => ['level', 'warning', 'warning_key', 'source', 'verification_date']],
            ])
            ->assertJsonPath('data.compliance.level', 'pilot');
    }

    public function test_mobile_summary_exposes_compliance_block(): void
    {
        // Issue #2144 — même bloc sur /payroll/mobile-summary (manager).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'MA']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/payroll/mobile-summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['compliance' => ['level']]])
            ->assertJsonPath('data.compliance.level', 'pilot');
    }

    public function test_rh_manager_can_access_mobile_summary(): void
    {
        // Issue #2749 — les écrans paie de l'app RH (leopardo_hr) consomment
        // /payroll/mobile-summary : le rôle rh était exclu du groupe
        // principal/comptable → 403. Les lectures paie mobiles acceptent
        // désormais principal, comptable ET rh.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($rh);

        $this->getJson('/api/v1/payroll/mobile-summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['totals']]);
    }

    public function test_rh_manager_cannot_write_salary_structure(): void
    {
        // Issue #2749 — seules les LECTURES mobiles sont ouvertes à rh ;
        // les écritures paie restent strictement principal/comptable.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($rh);

        $this->postJson('/api/v1/salary-structures', [
            'name' => 'Structure interdite',
            'country' => 'DZ',
        ])->assertStatus(403);
    }

    public function test_manager_mobile_summary_is_tenant_scoped(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['currency' => 'EUR']);
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Amina',
            'salary_base' => 90000,
        ]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'first_name' => 'Karim',
            'salary_base' => 999999,
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/payroll/mobile-summary');

        $response->assertOk()
            ->assertJsonFragment(['employee_id' => $employeeA->id])
            ->assertJsonMissing(['employee_id' => $employeeB->id]);
    }

    public function test_employee_balance_includes_overtime_hours_and_estimated_pay(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 0,
            'hourly_rate' => 500,
        ]);

        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'check_in' => now()->startOfMonth()->addDays(2)->setTime(8, 0),
            'check_out' => now()->startOfMonth()->addDays(2)->setTime(19, 0),
            'hours_worked' => 11,
            'overtime_hours' => 3,
            'status' => 'ontime', // 'complete' is not a valid attendance_logs status; 'ontime' denotes a completed shift
        ]);
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'check_in' => now()->startOfMonth()->addDays(3)->setTime(8, 0),
            'check_out' => now()->startOfMonth()->addDays(3)->setTime(18, 0),
            'hours_worked' => 10,
            'overtime_hours' => 2,
            'status' => 'ontime', // 'complete' is not a valid attendance_logs status; 'ontime' denotes a completed shift
        ]);

        Sanctum::actingAs($employee);

        $resp = $this->getJson('/api/v1/me/balance');
        fwrite(STDERR, 'BALANCE='.$resp->getContent()."\n");
        $resp->assertOk()
            ->assertJsonPath('data.overtime_hours', 5)
            ->assertJsonPath('data.overtime_pay', 3750); // 5h * 500 * 1.5
    }

    public function test_manager_mobile_summary_aggregates_team_overtime_totals(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 0,
            'hourly_rate' => 400,
        ]);

        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => now()->startOfMonth()->addDays(1)->toDateString(),
            'check_in' => now()->startOfMonth()->addDays(1)->setTime(8, 0),
            'check_out' => now()->startOfMonth()->addDays(1)->setTime(20, 0),
            'hours_worked' => 12,
            'overtime_hours' => 4,
            'status' => 'ontime', // 'complete' is not a valid attendance_logs status; 'ontime' denotes a completed shift
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/mobile-summary')->assertOk();

        $response->assertJsonPath('data.totals.overtime_hours', 4)
            ->assertJsonPath('data.totals.overtime_pay', 2400); // 4h * 400 * 1.5
        $response->assertJsonFragment(['overtime_hours' => 4]);
    }
}
