<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\PayrollRunValidated;
use App\Exceptions\DomainException;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Services\PayrollJournalEntryService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Journal des écritures salariales — flux Paie → Comptabilité (issue #5239,
 * Phase C, Partie 1).
 *
 * Un run de paie validé (DZ) produit automatiquement ses écritures
 * (événement PayrollRunValidated, dispatch additif dans validateRun) —
 * persistance EXACTE de PayrollAccountingExportService::journalLines()
 * (#5256) : équilibre débit = crédit par bulletin et par run, référence
 * PAYROLL-RUN-{id}, idempotence (UNIQUE), pays sans plan comptable → pending,
 * RBAC comptable/principal + isolation tenant (404 fail-closed).
 */
class AccountingJournalEntryTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function manager(Company $company, string $managerRole): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    /**
     * Run de paie DZ avec 2 bulletins validés identiques (brut 60 000,
     * cotisations salariales 5 000, impôt 3 000, avance 2 000 → net 50 000,
     * charges patronales 9 000) — mêmes montants que
     * PayrollAccountingExportJournalTest (#5256), golden calculé à la main.
     *
     * @return array{0: PayrollRun, 1: Company}
     */
    private function dzRun(string $slipStatus = 'validated', string $runStatus = 'locked', string $country = 'DZ'): array
    {
        $company = $this->company();
        $this->bindCompany($company);

        /** @var Employee $e1 */
        $e1 = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'matricule' => null]);
        /** @var Employee $e2 */
        $e2 = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Marie', 'last_name' => 'Martin', 'matricule' => null]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => $country,
            'status' => $runStatus,
        ]);

        foreach ([$e1, $e2] as $employee) {
            /** @var PaySlip $slip */
            $slip = PaySlip::create([
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employee_id' => $employee->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'gross_salary' => 60000,
                'total_deductions' => 10000,
                'net_salary' => 50000,
                'employer_contributions' => 9000,
                'total_cost' => 69000,
                'status' => $slipStatus,
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
        }

        return [$run, $company];
    }

    // ── US1 : génération automatique à la validation ───────────────────────

    public function test_validation_endpoint_dispatches_event_and_creates_entries(): void
    {
        // E2E : comptable valide un run calculé → l'événement additif
        // PayrollRunValidated est dispatché → le journal est alimenté.
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->dzRun(slipStatus: 'calculated', runStatus: 'calculated');
        $comptable = $this->manager($company, 'comptable');
        Sanctum::actingAs($comptable);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(200);

        $entries = AccountingJournalEntry::query()
            ->where('payroll_run_id', $run->id)
            ->orderBy('id')
            ->get();

        // 2 bulletins × 6 écritures (golden #5256).
        $this->assertCount(12, $entries);
        $this->assertSame((float) 138000.0, (float) $entries->sum('debit'));
        $this->assertSame((float) $entries->sum('debit'), (float) $entries->sum('credit'));
        $this->assertSame('PAYROLL-RUN-'.$run->id, $entries->first()->reference);
        $this->assertSame('payroll_run', $entries->first()->source);
        // Audit : created_by = l'acteur de la validation.
        $this->assertSame((int) $comptable->id, (int) $entries->first()->created_by);
    }

    public function test_dz_golden_journal_is_balanced_and_persisted(): void
    {
        [$run] = $this->dzRun();
        $service = app(PayrollJournalEntryService::class);

        $result = $service->generateForRun($run, actorId: 42);

        $this->assertSame('generated', $result['status']);
        $this->assertSame(12, $result['generated']);

        $entries = AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->get();
        $this->assertCount(12, $entries);

        // Totaux par compte (golden #5256, PCN §6.3) :
        // D 641 120 000 · D 645 18 000 · C 421 100 000 · C 431 28 000 ·
        // C 4421 6 000 · C 425 4 000.
        $totals = [];
        foreach ($entries as $entry) {
            $totals[$entry->account_code]['debit'] = ($totals[$entry->account_code]['debit'] ?? 0.0) + (float) $entry->debit;
            $totals[$entry->account_code]['credit'] = ($totals[$entry->account_code]['credit'] ?? 0.0) + (float) $entry->credit;
        }
        $this->assertSame(120000.0, $totals['641']['debit'] ?? 0.0);
        $this->assertSame(18000.0, $totals['645']['debit'] ?? 0.0);
        $this->assertSame(100000.0, $totals['421']['credit'] ?? 0.0);
        $this->assertSame(28000.0, $totals['431']['credit'] ?? 0.0);
        $this->assertSame(6000.0, $totals['4421']['credit'] ?? 0.0);
        $this->assertSame(4000.0, $totals['425']['credit'] ?? 0.0);

        // Équilibre par bulletin + débit OU crédit exclusif + traçabilité.
        foreach ($entries->groupBy('pay_slip_id') as $slipEntries) {
            $this->assertSame(
                (float) $slipEntries->sum('debit'),
                (float) $slipEntries->sum('credit'),
                'équilibre par bulletin'
            );
            foreach ($slipEntries as $entry) {
                $this->assertTrue(
                    ((float) $entry->debit > 0.0) xor ((float) $entry->credit > 0.0),
                    'débit OU crédit exclusif'
                );
            }
        }

        $this->assertSame('2026-06-30', $entries->first()->entry_date->toDateString());
        $this->assertSame(42, (int) $entries->first()->created_by);
        $this->assertNotNull($entries->first()->pay_slip_id);
        $this->assertNotNull($entries->first()->employee_id);
    }

    public function test_regeneration_is_idempotent(): void
    {
        [$run] = $this->dzRun();
        $service = app(PayrollJournalEntryService::class);

        $first = $service->generateForRun($run);
        $second = $service->generateForRun($run);

        $this->assertSame(12, $first['generated']);
        $this->assertSame(0, $second['generated']);
        $this->assertSame(12, AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_non_validated_run_is_rejected(): void
    {
        [$run] = $this->dzRun(runStatus: 'calculated');
        $service = app(PayrollJournalEntryService::class);

        try {
            $service->generateForRun($run);
            $this->fail('Un run non validé doit être refusé (règle #2223).');
        } catch (DomainException $exception) {
            $this->assertSame('PAYROLL_RUN_NOT_VALIDATED', $exception->errorCode());
            $this->assertSame(422, $exception->statusCode());
        }

        $this->assertSame(0, AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_country_without_chart_of_accounts_returns_pending(): void
    {
        [$run] = $this->dzRun(country: 'ZZ');
        $service = app(PayrollJournalEntryService::class);

        $result = $service->generateForRun($run);

        $this->assertSame('pending', $result['status']);
        $this->assertSame(0, $result['generated']);
        $this->assertSame(0, AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_event_listener_creates_entries(): void
    {
        [$run] = $this->dzRun();
        $comptable = $this->manager($this->company(), 'comptable');

        PayrollRunValidated::dispatch($run, (int) $comptable->id);

        $this->assertSame(12, AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_command_generates_entries(): void
    {
        [$run] = $this->dzRun();

        $this->artisan('accounting:generate-payroll-entries', ['--run' => (string) $run->id])
            ->expectsOutputToContain('statut generated')
            ->assertSuccessful();

        $this->assertSame(12, AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->count());

        // Idempotent : une seconde exécution n'ajoute rien.
        $this->artisan('accounting:generate-payroll-entries', ['--run' => (string) $run->id])
            ->expectsOutputToContain('0 écriture(s)')
            ->assertSuccessful();
    }

    // ── US3 : RBAC + isolation tenant ───────────────────────────────────────

    public function test_comptable_can_list_and_show_entries(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->dzRun();
        app(PayrollJournalEntryService::class)->generateForRun($run);
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $list = $this->getJson('/api/v1/accounting/journal-entries?payroll_run_id='.$run->id);

        $list->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('data.0.reference', 'PAYROLL-RUN-'.$run->id);

        $id = $list->json('data.0.id');
        $this->getJson("/api/v1/accounting/journal-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.account_code', '641');
    }

    public function test_principal_can_read_entries(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->dzRun();
        app(PayrollJournalEntryService::class)->generateForRun($run);
        Sanctum::actingAs($this->manager($company, 'principal'));

        $this->getJson('/api/v1/accounting/journal-entries?payroll_run_id='.$run->id)->assertOk();
    }

    public function test_rbac_rh_and_employee_are_forbidden(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        Sanctum::actingAs($this->manager($company, 'rh'));
        $this->getJson('/api/v1/accounting/journal-entries')->assertStatus(403);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee', 'manager_role' => null]);
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/accounting/journal-entries')->assertStatus(403);
    }

    public function test_cross_tenant_entries_are_invisible(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        [$run] = $this->dzRun();
        app(PayrollJournalEntryService::class)->generateForRun($run);
        $entryId = AccountingJournalEntry::query()->where('payroll_run_id', $run->id)->first()->id;

        // Autre entreprise : 404 fail-closed sur le détail, liste vide.
        $other = $this->company();
        $this->bindCompany($other);
        Sanctum::actingAs($this->manager($other, 'comptable'));

        $this->getJson("/api/v1/accounting/journal-entries/{$entryId}")->assertStatus(404);
        $this->getJson('/api/v1/accounting/journal-entries')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
