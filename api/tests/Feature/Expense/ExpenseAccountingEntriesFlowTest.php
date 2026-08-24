<?php

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Expense\Domain\Models\ExpenseAccountingEntry;
use App\Modules\Expense\Infrastructure\Services\ExpenseAccountingEntryService;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5235 — Phase C : écritures comptables des notes de frais.
 *
 * À l'approbation d'un ExpenseClaim (statut `approved` via `update()`
 * d'instance — events Eloquent déclenchés), l'observer
 * `ExpenseAccountingEntryObserver` persiste 2 lignes équilibrées
 * (D 625 frais généraux / C 512 banque) avec référence `EXPENSE-CLAIM-{id}`.
 *
 * Méthodologie : golden — note 1 000,00 → débit = crédit = 1 000,00.
 */
class ExpenseAccountingEntriesFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_approval_triggers_automatic_generation(): void
    {
        [$company, $claim] = $this->claimWithStatus('submitted');

        // Reproduit le flux réel : ExpenseClaimController::approve() fait un
        // update() d'instance (events Eloquent) → l'observer génère.
        $claim->update([
            'status' => 'approved',
            'approved_by' => (string) $company->id,
            'approved_at' => now(),
        ]);

        $this->assertSame(2, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
        $this->assertSame(
            0.0,
            (new ExpenseAccountingEntryService)->balanceForClaim($claim)
        );
    }

    public function test_golden_approved_claim_entries_are_balanced(): void
    {
        [$company, $claim] = $this->claimWithStatus('approved');

        $service = new ExpenseAccountingEntryService;
        $count = $service->generateForClaim($claim);
        $this->assertSame(2, $count);

        $entries = $service->entriesForClaim($claim);
        $this->assertCount(2, $entries);

        $byCode = [];
        foreach ($entries as $entry) {
            $this->assertSame("EXPENSE-CLAIM-{$claim->id}", $entry->reference);
            $this->assertSame($claim->company_id, $entry->company_id);
            $byCode[$entry->account_code] = $entry;
        }

        $this->assertSame(1000.0, $byCode['625']->debit);
        $this->assertSame(0.0, $byCode['625']->credit);
        $this->assertSame(1000.0, $byCode['512']->credit);
        $this->assertSame(0.0, $byCode['512']->debit);

        $this->assertSame(0.0, $service->balanceForClaim($claim));
    }

    public function test_regeneration_is_idempotent(): void
    {
        [$company, $claim] = $this->claimWithStatus('approved');

        $service = new ExpenseAccountingEntryService;
        $this->assertSame(2, $service->generateForClaim($claim));
        $this->assertSame(2, $service->generateForClaim($claim));

        $this->assertSame(2, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
    }

    public function test_generation_requires_approved_claim(): void
    {
        [$company, $claim] = $this->claimWithStatus('submitted');

        $service = new ExpenseAccountingEntryService;
        $this->expectException(\RuntimeException::class);
        $service->generateForClaim($claim);
    }

    public function test_tenant_isolation(): void
    {
        [$companyA, $claimA] = $this->claimWithStatus('approved');
        [$companyB, $claimB] = $this->claimWithStatus('approved');

        $service = new ExpenseAccountingEntryService;
        $service->generateForClaim($claimA);
        $service->generateForClaim($claimB);

        $this->assertSame(2, $service->entriesForClaim($claimA)->count());
        $this->assertSame(2, $service->entriesForClaim($claimB)->count());
        $this->assertSame(
            2,
            ExpenseAccountingEntry::query()->where('company_id', $companyA->id)->count()
        );
    }

    public function test_observer_ignores_non_approved_saves(): void
    {
        [$company, $claim] = $this->claimWithStatus('draft');

        $claim->update(['title' => 'toujours brouillon']);

        $this->assertSame(0, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
    }

    public function test_approval_never_blocked_by_observer(): void
    {
        [$company, $claim] = $this->claimWithStatus('submitted');

        // L'observer est non-bloquant : même si la génération échoue,
        // l'approbation doit passer. On force un échec en remplaçant le
        // service par un mock qui lève une exception.
        $failing = $this->createMock(ExpenseAccountingEntryService::class);
        $failing->method('generateForClaim')->willThrowException(new \RuntimeException('panne simulée'));
        $this->app->instance(ExpenseAccountingEntryService::class, $failing);

        Log::spy();

        $claim->update([
            'status' => 'approved',
            'approved_by' => (string) $company->id,
            'approved_at' => now(),
        ]);

        // L'approbation a réussi (statut en base) → observer non bloquant.
        $this->assertSame('approved', $claim->fresh()->status);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_rejection_after_approval_removes_entries(): void
    {
        [$company, $claim] = $this->claimWithStatus('approved');

        $service = new ExpenseAccountingEntryService;
        $service->generateForClaim($claim);
        $this->assertSame(2, $service->entriesForClaim($claim)->count());

        // Rejet après approbation (workflow autorisé) → écritures supprimées.
        $claim->update(['status' => 'rejected']);

        $this->assertSame(0, $service->entriesForClaim($claim)->count());
    }

    // ── API ────────────────────────────────────────────────────────────────

    public function test_api_index_lists_entries_for_principal_only(): void
    {
        [$company, $claim] = $this->claimWithStatus('approved');

        $service = new ExpenseAccountingEntryService;
        $service->generateForClaim($claim);

        // RH (manager rh) → 403 (lecture réservée principal/comptable)
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        Sanctum::actingAs($rh);
        $this->getJson("/api/v1/expense-claims/{$claim->id}/accounting-entries")
            ->assertForbidden();

        // Employé → 403
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);
        $this->getJson("/api/v1/expense-claims/{$claim->id}/accounting-entries")
            ->assertForbidden();

        // Principal → 200
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/expense-claims/{$claim->id}/accounting-entries")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'account_code', 'debit', 'credit', 'reference']]]);
    }

    public function test_api_regenerate_requires_comptable_role(): void
    {
        [$company, $claim] = $this->claimWithStatus('approved');

        // RH (manager rh) → 403
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        Sanctum::actingAs($rh);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // Employé → 403
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // Principal (lecture seule) → 403 sur régénération
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // Comptable → 200
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $company->id]);
        Sanctum::actingAs($comptable);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertOk()
            ->assertJsonPath('generated_lines', 2)
            ->assertJsonPath('balance', 0);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: ExpenseClaim}
     */
    private function claimWithStatus(string $status): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var ExpenseClaim $claim */
        $claim = ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Déplacement client Alger',
            'description' => 'Mission commerciale',
            'total_amount' => 1000.0,
            'currency' => 'DZD',
            'status' => $status,
            'submitted_at' => in_array($status, ['submitted', 'approved'], true) ? now() : null,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        return [$company, $claim];
    }
}
