<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\WarmPaySlipPdfPathsForPayrollRunJob;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class PayrollRunControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private function bindFakePayrollCalculator(string $confidenceLevel = 'production', bool $expectCalculateRun = true): void
    {
        /** @var PayrollCalculator&MockInterface $calculator */
        $calculator = Mockery::mock(PayrollCalculator::class);
        if ($expectCalculateRun) {
            $calculator
                ->shouldReceive('calculateRun')
                ->once()
                ->andReturnUsing(
                    function (PayrollRun $run): PayrollRun {
                        $employee = Employee::query()
                            ->where('company_id', $run->company_id)
                            ->where('status', 'active')
                            ->firstOrFail();

                        PaySlip::query()->create([
                            'payroll_run_id' => $run->id,
                            'company_id' => $run->company_id,
                            'employee_id' => $employee->id,
                            'period_start' => $run->period_start,
                            'period_end' => $run->period_end,
                            'gross_salary' => 100000,
                            'total_deductions' => 25000,
                            'net_salary' => 75000,
                            'employer_contributions' => 12000,
                            'total_cost' => 112000,
                            'working_days' => 22,
                            'actual_days_worked' => 22,
                            'overtime_hours' => 0,
                            'status' => 'calculated',
                        ]);

                        $run->update([
                            'status' => 'calculated',
                            'total_gross' => 100000,
                            'total_deductions' => 25000,
                            'total_net' => 75000,
                            'total_employer_cost' => 112000,
                            'employee_count' => 1,
                            'calculated_at' => now(),
                        ]);

                        return $run->refresh();
                    }
                );
        }

        // Issue #2332 — le contrôleur résout désormais les règles du pays
        // avant le calcul (garde placeholder) : le fake doit servir `getRules`.
        /** @var Expectation $getRulesExpectation */
        $getRulesExpectation = $calculator->shouldReceive('getRules');
        $getRulesExpectation->andReturnUsing(function () use ($confidenceLevel): CountryRulesInterface {
            /** @var CountryRulesInterface&MockInterface $rules */
            $rules = Mockery::mock(CountryRulesInterface::class);
            /** @var Expectation $confidenceExpectation */
            $confidenceExpectation = $rules->shouldReceive('confidenceLevel');
            $confidenceExpectation->andReturn($confidenceLevel);

            return $rules;
        });

        $this->app->instance(PayrollCalculator::class, $calculator);
    }

    public function test_manager_can_list_payroll_runs(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_manager_can_create_payroll_run(): void
    {
        // #1905 : le pays légal du tenant doit correspondre au country_code
        // du run — la factory tire un pays aléatoire sinon (test flaky).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.country_code', 'DZ');
    }

    public function test_manager_can_view_payroll_run(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'FR',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}");
        $response->assertOk();
        $response->assertJsonPath('data.country_code', 'FR');
    }

    public function test_employee_cannot_create_payroll_run(): void
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

    public function test_payroll_run_summary(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
            'total_gross' => 500000,
            'total_deductions' => 100000,
            'total_net' => 400000,
            'employee_count' => 10,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/summary");
        $response->assertOk();
    }

    public function test_manager_can_calculate_payroll_run_with_calculator_contract(): void
    {
        $this->bindFakePayrollCalculator();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'calculated');
        $response->assertJsonPath('data.pay_slips_count', 1);
        $this->assertDatabaseHas('pay_slips', [
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'status' => 'calculated',
        ]);
    }

    public function test_calculate_run_placeholder_country_requires_acknowledgement(): void
    {
        // Issue #2332 — pays placeholder : le calcul du run est refusé sans
        // confirmation explicite, le run ne doit PAS changer de statut et
        // calculateRun ne doit pas être appelé.
        $this->bindFakePayrollCalculator(confidenceLevel: 'placeholder', expectCalculateRun: false);
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'BJ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate");

        $response->assertStatus(422);
        $response->assertJsonPath('errors.acknowledge_placeholder.0', __('payroll.placeholder_acknowledge_required', ['country' => 'BJ']));
        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'placeholder_warning_acknowledged',
            'company_id' => $company->id,
        ]);
    }

    public function test_calculate_run_placeholder_country_with_acknowledgement_audits_and_runs(): void
    {
        // Issue #2332 — confirmation explicite : le calcul s'exécute et
        // l'acceptation est tracée (AuditLog placeholder_warning_acknowledged).
        $this->bindFakePayrollCalculator(confidenceLevel: 'placeholder');
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'BJ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate", [
            'acknowledge_placeholder' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'calculated');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'placeholder_warning_acknowledged',
            'company_id' => $company->id,
            'user_id' => $manager->id,
        ]);

        $log = AuditLog::query()
            ->where('action', 'placeholder_warning_acknowledged')
            ->where('company_id', $company->id)
            ->firstOrFail();
        $this->assertSame('BJ', $log->new_values['country_code']);
        $this->assertSame('payroll_run_calculate', $log->new_values['context']);
        $this->assertSame($run->id, $log->new_values['run_id']);
    }

    public function test_calculate_run_non_placeholder_country_does_not_require_acknowledgement(): void
    {
        // Issue #2332 — pays pilot/production : aucun paramètre requis, aucun audit.
        $this->bindFakePayrollCalculator(confidenceLevel: 'pilot');
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate");

        $response->assertOk();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'placeholder_warning_acknowledged',
            'company_id' => $company->id,
        ]);
    }

    public function test_manager_can_validate_calculated_run_and_slips(): void
    {
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
        ]);
        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 100000,
            'total_deductions' => 25000,
            'net_salary' => 75000,
            'employer_contributions' => 12000,
            'total_cost' => 112000,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'calculated',
        ]);
        PaySlipLine::query()->create([
            'pay_slip_id' => $slip->id,
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => 100000,
            'rate' => 1,
            'amount' => 100000,
            'order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/validate");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'validated');
        $this->assertDatabaseHas('pay_slips', [
            'payroll_run_id' => $run->id,
            'status' => 'validated',
        ]);
        $this->assertSame($manager->id, $run->fresh()->validated_by);

        $slip->refresh();
        $this->assertNotNull($slip->pdf_path);
        Storage::disk('local')->assertExists((string) $slip->pdf_path);
    }

    public function test_validate_dispatches_pay_slip_pdf_warmup_job(): void
    {
        Queue::fake();

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
        ]);
        PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 100000,
            'total_deductions' => 25000,
            'net_salary' => 75000,
            'employer_contributions' => 12000,
            'total_cost' => 112000,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'calculated',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertOk();

        Queue::assertPushed(WarmPaySlipPdfPathsForPayrollRunJob::class, function (WarmPaySlipPdfPathsForPayrollRunJob $job) use ($run): bool {
            return $job->payrollRunId === $run->id;
        });
    }

    public function test_manager_can_cancel_draft_run_but_not_paid_run(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $draft = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);
        $paid = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'status' => 'paid',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$draft->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/v1/payroll-runs/{$paid->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_payroll_runs_are_isolated_by_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $ownRun = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);
        $otherRun = PayrollRun::create([
            'company_id' => $otherCompany->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/payroll-runs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRun->id);

        $this->getJson("/api/v1/payroll-runs/{$otherRun->id}")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$otherRun->id}/cancel")->assertNotFound();
    }

    // ── #1951 : « pays supporté » = registre ET règles de paie ─────────────

    public function test_run_creation_accepts_gb_us_now_that_rules_exist(): void
    {
        // #1951 : GB/US étaient « display-only » (CountryDefaults sans règles
        // de paie) → refusés 422. Depuis #5255, les packs EN sont livrés
        // (pilot 2026-27) : la création de run est acceptée comme pour les
        // autres pays supportés (le pays du run reste verrouillé sur le pays
        // du tenant — PAYROLL_RUN_COUNTRY_MISMATCH).
        /** @var Company $gbCompany */
        $gbCompany = Company::factory()->create(['country' => 'GB', 'currency' => 'GBP', 'timezone' => 'Europe/London']);
        /** @var Employee $gbManager */
        $gbManager = Employee::factory()->manager()->create(['company_id' => $gbCompany->id]);

        Sanctum::actingAs($gbManager);

        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'GB',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'GB');

        /** @var Company $usCompany */
        $usCompany = Company::factory()->create(['country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York']);
        /** @var Employee $usManager */
        $usManager = Employee::factory()->manager()->create(['company_id' => $usCompany->id]);

        Sanctum::actingAs($usManager);

        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'US',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'US');

        // La garde reste active pour un code totalement inconnu du registre.
        Sanctum::actingAs($gbManager);

        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'ZZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_run_creation_accepts_country_with_rules(): void
    {
        // #1951 : CI (CEDEAO, règles résolubles) accepté — le contrat partagé
        // du moteur remplace la liste in: hardcodée.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF', 'timezone' => 'Africa/Abidjan']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'CI',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'CI');
    }
}
