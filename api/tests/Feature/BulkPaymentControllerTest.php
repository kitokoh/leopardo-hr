<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProcessBulkPaymentJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Expectation;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-PAY-005 — "Selection multiple batch async recap erreurs partielles":
 * a manager must be able to select a specific subset of pay slips to pay
 * in a batch instead of always paying the whole run, with the batch still
 * processed asynchronously.
 */
class BulkPaymentControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // QA #2997 — les claims Redis `bulk_pay:*` ont un TTL de 6 h et les
        // IDs de payroll_run sont réutilisés entre runs de test : sans purge,
        // un run déjà claimé par un test précédent bloque le suivant (409).
        // NB : avec predis + préfixe, keys() retourne des clés déjà préfixées
        // (un del() ré-appliquerait le préfixe → échec silencieux). flushdb est
        // fiable et sûr : la suite tourne en séquentiel dans un job CI dédié.
        try {
            Redis::connection('default')->flushdb();
        } catch (\Throwable) {
            // Redis indisponible : les tests continuent (garde non bloquante).
        }
    }

    public function test_manager_can_bulk_pay_a_selected_subset_of_pay_slips(): void
    {
        Bus::fake();

        [$company, $manager, $run, $slipA, $slipB] = $this->fixture();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay", [
            'pay_slip_ids' => [$slipA->id],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('payroll_run_id', $run->id)
            ->assertJsonPath('selected_pay_slip_count', 1);

        Bus::assertDispatched(
            ProcessBulkPaymentJob::class,
            fn (ProcessBulkPaymentJob $job): bool => $job->payrollRunId === $run->id
                && $job->triggeredById === $manager->id
                && $job->paySlipIds === [$slipA->id]
        );
    }

    public function test_manager_can_bulk_pay_the_whole_run_when_no_selection_is_given(): void
    {
        Bus::fake();

        [$company, $manager, $run] = $this->fixture();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay");

        $response->assertAccepted()
            ->assertJsonPath('selected_pay_slip_count', null);

        Bus::assertDispatched(
            ProcessBulkPaymentJob::class,
            fn (ProcessBulkPaymentJob $job): bool => $job->payrollRunId === $run->id
                && $job->paySlipIds === null
        );
    }

    /**
     * @return array{0: Company, 1: Employee, 2: PayrollRun, 3: PaySlip, 4: PaySlip}
     */
    private function fixture(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'validated',
            'employee_count' => 2,
            'total_gross' => 240000,
            'total_deductions' => 44000,
            'total_net' => 196000,
        ]);

        $employeeA = Employee::factory()->create(['company_id' => $company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id]);

        $slipA = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        $slipB = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        return [$company, $manager, $run, $slipA, $slipB];
    }

    public function test_double_dispatch_is_rejected_with_409(): void
    {
        // QA #2997 — garde ATOMIQUE (SET NX) : un second dispatch pendant un
        // bulk-pay en cours est refusé en 409 (avant : fenêtre TOCTOU entre
        // le get et le dispatch → deux jobs pouvaient traiter les mêmes slips).
        Bus::fake();

        [$company, $manager, $run, $slipA, $slipB] = $this->fixture();
        Sanctum::actingAs($manager);

        // 1er dispatch → accepté (claim posé en Redis, statut 'starting')
        $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay")
            ->assertAccepted()
            ->assertJsonPath('status', 'accepted');

        // 2e dispatch immédiat → 409 (claim déjà posé)
        $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Un paiement en masse est déjà en cours.');

        // Un seul job dispatché au total
        Bus::assertDispatchedTimes(ProcessBulkPaymentJob::class, 1);
    }

    public function test_bulk_pay_fails_closed_with_503_when_redis_is_unavailable(): void
    {
        // #3857 : FAIL-CLOSED. Redis est le coordinateur anti-doublon (claim
        // NX du run) : s'il est indisponible, un dispatch sans claim
        // laisserait deux requêtes concurrentes lancer deux jobs qui
        // paieraient 2× les mêmes bulletins (mouvement d'argent). On refuse
        // avec 503 et AUCUN job n'est dispatché.
        Bus::fake();

        [$company, $manager, $run, $slipA, $slipB] = $this->fixture();
        Sanctum::actingAs($manager);

        $client = Mockery::mock(PhpRedisConnection::class);
        /** @var Expectation $setExpectation */
        $setExpectation = $client->shouldReceive('set');
        $setExpectation->andThrow(new \RuntimeException('Redis connection refused'));
        /** @var Expectation $getExpectation */
        $getExpectation = $client->shouldReceive('get');
        $getExpectation->andReturn(null);
        Redis::shouldReceive('connection')->with('default')->andReturn($client);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay")
            ->assertStatus(503)
            ->assertJsonPath('error', 'BULK_PAYMENT_COORDINATOR_UNAVAILABLE');

        Bus::assertNotDispatched(ProcessBulkPaymentJob::class);
    }
}
