<?php

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use Tests\TestCase;

/**
 * Programme FOCUS — F-11 : clôture 2 étapes (RH → comptable), verrouillage,
 * audit trail, refus de modification post-clôture.
 */
class PayrollClosingTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;


    private function makeRun(Company $company, string $status): PayrollRun
    {
        return PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => $status,
        ]);
    }

    public function test_validation_rh_then_comptable_lock_flow(): void
    {
        $company = Company::factory()->create();
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company, PayrollRun::STATUS_CALCULATED);

        $service = new PayrollClosingService();

        // Étape 1 : RH valide.
        $service->validateRh($run, $rh);
        $this->assertSame(PayrollRun::STATUS_VALIDATED, $run->fresh()->status);
        $this->assertSame($rh->id, $run->fresh()->validated_by);

        // Étape 2 : comptable verrouille.
        $service->lock($run, $comptable);
        $this->assertSame(PayrollRun::STATUS_LOCKED, $run->fresh()->status);
        $this->assertSame($comptable->id, $run->fresh()->locked_by);

        // Audit trail : 2 entrées.
        $logs = AuditLog::where('company_id', $company->id)
            ->where('auditable_type', $run->getMorphClass())
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['payroll_run_validated', 'payroll_run_locked'], $logs->pluck('action')->values()->all());
    }

    public function test_lock_requires_rh_validation_first(): void
    {
        $company = Company::factory()->create();
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company, PayrollRun::STATUS_CALCULATED);

        $this->expectException(PayrollAlreadyValidatedException::class);
        (new PayrollClosingService())->lock($run, $comptable);
    }

    public function test_locked_run_cannot_be_recalculated(): void
    {
        $company = Company::factory()->create();
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company, PayrollRun::STATUS_CALCULATED);

        $service = new PayrollClosingService();
        $service->validateRh($run, $rh);
        $service->lock($run, $comptable);

        $this->expectException(PayrollRunLockedException::class);
        (new PayrollCalculator())->calculateRun($run->fresh());
    }

    public function test_unlock_requires_reason_and_traces_it(): void
    {
        $company = Company::factory()->create();
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company, PayrollRun::STATUS_CALCULATED);

        $service = new PayrollClosingService();
        $service->validateRh($run, $rh);
        $service->lock($run, $comptable);

        // Raison vide → refus.
        try {
            $service->unlock($run, $comptable, '');
            $this->fail('Unlock without reason should throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('raison', $e->getMessage());
        }

        // Déverrouillage motivé.
        $service->unlock($run, $comptable, 'Correction IRG demandée par le comptable');
        $this->assertSame(PayrollRun::STATUS_VALIDATED, $run->fresh()->status);

        $unlockLog = AuditLog::where('action', 'payroll_run_unlocked')->first();
        $this->assertNotNull($unlockLog);
        $this->assertSame('Correction IRG demandée par le comptable', $unlockLog->metadata['reason']);
    }
}
