<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\UnbalancedPayrollEntriesException;
use App\Modules\Payroll\Domain\Models\PayrollAccountingEntry;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\PayrollAccountingEntryService;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5239 — Phase C : écritures salariales automatiques.
 *
 * À la validation RH d'un run (`AuditLog` action `payroll_run_validated`),
 * les écritures comptables sont générées depuis
 * `PayrollAccountingExportService::journalLines()` (socle #5256) et
 * persistées dans `payroll_accounting_entries`.
 *
 * Méthodologie : golden DZ calculé à la main (mêmes montants que le socle
 * #5256 — D 641 120 000 · D 645 18 000 · C 421 100 000 · C 431 28 000 ·
 * C 4421 6 000 · C 425 4 000 ; débit = crédit = 138 000).
 */
class PayrollAccountingEntriesFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_validation_audit_triggers_automatic_generation(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'calculated');

        // Reproduit la séquence réelle de PayrollClosingService::validateRh() :
        // 1) mass-update du run en `validated` (aucun event Eloquent émis),
        // 2) écriture de l'audit `payroll_run_validated` (le signal observé).
        PayrollRun::query()->whereKey($run->id)->update([
            'status' => PayrollRun::STATUS_VALIDATED,
            'validated_at' => now(),
        ]);

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => null,
            'action' => 'payroll_run_validated',
            'auditable_type' => PayrollRun::class,
            'auditable_id' => $run->id,
            'old_values' => ['status' => 'calculated'],
            'new_values' => ['status' => 'validated'],
        ]);

        $this->assertSame(12, PayrollAccountingEntry::query()->where('payroll_run_id', $run->id)->count());
        $this->assertSame(0.0, (new PayrollAccountingEntryService(new PayrollAccountingExportService))->balanceForRun($run));
    }

    public function test_golden_dz_entries_are_balanced_and_use_pcn_accounts(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollAccountingEntryService(new PayrollAccountingExportService);
        $count = $service->generateForRun($run);
        $this->assertSame(12, $count);

        $entries = $service->entriesForRun($run);
        $this->assertCount(12, $entries);

        $totals = [
            '641' => ['debit' => 0.0, 'credit' => 0.0],
            '645' => ['debit' => 0.0, 'credit' => 0.0],
            '421' => ['debit' => 0.0, 'credit' => 0.0],
            '431' => ['debit' => 0.0, 'credit' => 0.0],
            '4421' => ['debit' => 0.0, 'credit' => 0.0],
            '425' => ['debit' => 0.0, 'credit' => 0.0],
        ];
        foreach ($entries as $entry) {
            $this->assertSame("PAYROLL-RUN-{$run->id}", $entry->reference);
            $this->assertSame($run->company_id, $entry->company_id);
            $this->assertArrayHasKey($entry->account_code, $totals);
            $totals[$entry->account_code]['debit'] += $entry->debit;
            $totals[$entry->account_code]['credit'] = ($totals[$entry->account_code]['credit'] ?? 0.0) + $entry->credit;
        }

        $this->assertSame(120000.0, $totals['641']['debit']);
        $this->assertSame(18000.0, $totals['645']['debit']);
        $this->assertSame(100000.0, $totals['421']['credit']);
        $this->assertSame(28000.0, $totals['431']['credit']);
        $this->assertSame(6000.0, $totals['4421']['credit']);
        $this->assertSame(4000.0, $totals['425']['credit']);

        $balance = $service->balanceForRun($run);
        $this->assertSame(0.0, $balance);
    }

    public function test_regeneration_is_idempotent(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollAccountingEntryService(new PayrollAccountingExportService);
        $this->assertSame(12, $service->generateForRun($run));
        $this->assertSame(12, $service->generateForRun($run)); // 2e appel → remplacement, pas doublon

        $this->assertSame(12, PayrollAccountingEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_generation_requires_validated_run(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'calculated');

        $service = new PayrollAccountingEntryService(new PayrollAccountingExportService);
        $this->expectException(\RuntimeException::class);
        $service->generateForRun($run);
    }

    public function test_generation_rejects_unbalanced_journal(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'validated');

        // Mock du socle : journal DÉSÉQUILIBRÉ (débit 100, crédit 90) → le
        // service doit refuser la persistance (US3 de la spec #5239).
        $export = $this->createMock(PayrollAccountingExportService::class);
        $export->method('journalLines')->willReturn([
            [
                'date' => '2026-06-30',
                'company_id' => (string) $run->company_id,
                'payroll_run_id' => $run->id,
                'pay_slip_id' => 1,
                'employee_id' => 1,
                'account_code' => '641',
                'account_label' => 'Salaires bruts',
                'debit' => 100.0,
                'credit' => 0.0,
                'reference' => "PAYROLL-RUN-{$run->id}",
            ],
            [
                'date' => '2026-06-30',
                'company_id' => (string) $run->company_id,
                'payroll_run_id' => $run->id,
                'pay_slip_id' => 1,
                'employee_id' => 1,
                'account_code' => '421',
                'account_label' => 'Salaires à payer',
                'debit' => 0.0,
                'credit' => 90.0,
                'reference' => "PAYROLL-RUN-{$run->id}",
            ],
        ]);

        $service = new PayrollAccountingEntryService($export);

        try {
            $service->generateForRun($run);
            $this->fail('UnbalancedPayrollEntriesException attendue pour un journal déséquilibré.');
        } catch (UnbalancedPayrollEntriesException $e) {
            $this->assertStringContainsString('déséquilibré', $e->getMessage());
        }

        // Aucune ligne ne doit être persistée.
        $this->assertSame(0, PayrollAccountingEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_tenant_isolation(): void
    {
        [$companyA, $runA] = $this->runWithSlips('DZ', status: 'validated');
        [$companyB, $runB] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollAccountingEntryService(new PayrollAccountingExportService);
        $service->generateForRun($runA);
        $service->generateForRun($runB);

        $this->assertSame(12, $service->entriesForRun($runA)->count());
        $this->assertSame(12, $service->entriesForRun($runB)->count());
        $this->assertSame(
            12,
            PayrollAccountingEntry::query()->where('company_id', $companyA->id)->count()
        );
    }

    public function test_observer_ignores_unrelated_audit_actions(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'calculated');

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => null,
            'action' => 'payroll_run_locked', // action sans rapport
            'auditable_type' => PayrollRun::class,
            'auditable_id' => $run->id,
            'old_values' => [],
            'new_values' => [],
        ]);

        $this->assertSame(0, PayrollAccountingEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_observer_failure_is_logged_not_propagated(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'calculated');

        // Run en statut calculated : la génération échoue (RuntimeException) mais
        // l'observer doit LOGGER sans propager (la validation ne casse pas).
        Log::spy();

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => null,
            'action' => 'payroll_run_validated',
            'auditable_type' => PayrollRun::class,
            'auditable_id' => $run->id,
            'old_values' => ['status' => 'calculated'],
            'new_values' => ['status' => 'validated'],
        ]);

        Log::spy()->shouldHaveReceived('error')->once();
    }

    // ── API ────────────────────────────────────────────────────────────────

    public function test_api_index_lists_entries_for_principal(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'validated');

        $service = new PayrollAccountingEntryService(new PayrollAccountingExportService);
        $service->generateForRun($run);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/accounting-entries")
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonStructure(['data' => [['id', 'account_code', 'debit', 'credit', 'reference']]]);
    }

    public function test_api_regenerate_requires_comptable_role(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', status: 'validated');

        // Principal (lecture seule) → 403
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // RH → 403 (pas dans le groupe manager payroll)
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        Sanctum::actingAs($rh);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // Comptable → 200
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $company->id]);
        Sanctum::actingAs($comptable);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/accounting-entries/regenerate")
            ->assertOk()
            ->assertJsonPath('generated_lines', 12)
            ->assertJsonPath('balance', 0);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: PayrollRun}
     */
    private function runWithSlips(string $country = 'DZ', string $status = 'validated'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'matricule' => null,
        ]);
        /** @var Employee $employee2 */
        $employee2 = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'matricule' => null,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => $country,
            'status' => $status,
        ]);

        foreach ([$employee, $employee2] as $target) {
            /** @var PaySlip $slip */
            $slip = PaySlip::create([
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employee_id' => $target->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'gross_salary' => 60000,
                'total_deductions' => 10000,
                'net_salary' => 50000,
                'employer_contributions' => 9000,
                'total_cost' => 69000,
                'status' => $status,
            ]);

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations salariales',
                'type' => 'deduction',
                'amount' => 5000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Impot sur le revenu',
                'type' => 'deduction',
                'amount' => 3000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Avance',
                'type' => 'deduction',
                'amount' => 2000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations patronales',
                'type' => 'employer_contribution',
                'amount' => 9000,
            ]);
        }

        return [$company, $run];
    }
}
