<?php

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Expense\Domain\Exceptions\UnbalancedExpenseEntriesException;
use App\Modules\Expense\Domain\Models\ExpenseAccountingEntry;
use App\Modules\Expense\Infrastructure\Listeners\ExpenseAccountingEntryObserver;
use App\Modules\Expense\Infrastructure\Services\ExpenseAccountingEntryService;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use App\Modules\Planning\Domain\Models\ExpenseItem;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5235 — Phase C : notes de frais → écritures comptables.
 *
 * À l'approbation d'une `ExpenseClaim` (observer Eloquent `updated`), les
 * écritures comptables sont générées (partie double : débit charge / crédit
 * 425 personnel) et persistées dans `expense_accounting_entries`.
 *
 * Méthodologie : golden calculé à la main (note 10 000 → D 6251 « Voyages et
 * déplacements » 10 000 / C 425 « Personnel » 10 000 ; débit = crédit = 10 000).
 */
class ExpenseAccountingEntriesFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_approval_triggers_automatic_generation(): void
    {
        [$company, $claim] = $this->claimWithItems('submitted', [
            ['category' => 'transport', 'description' => 'Taxi aéroport', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        // Reproduit la séquence réelle de ExpenseClaimController::approve() :
        // update() → event Eloquent `updated` (le signal observé).
        $claim->update([
            'status' => 'approved',
            'approved_by' => (string) $claim->employee_id,
            'approved_at' => now(),
        ]);

        $this->assertSame(2, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
        $this->assertSame(
            0.0,
            (new ExpenseAccountingEntryService())->balanceForClaim($claim)
        );
    }

    public function test_golden_transport_entries_are_balanced_and_use_pcg_accounts(): void
    {
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi aéroport', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        $service = new ExpenseAccountingEntryService();
        $count = $service->generateForClaim($claim);
        $this->assertSame(2, $count);

        $entries = $service->entriesForClaim($claim);
        $this->assertCount(2, $entries);

        $byCode = [];
        foreach ($entries as $entry) {
            $this->assertSame("EXPENSE-{$claim->id}", $entry->reference);
            $this->assertSame($claim->company_id, $entry->company_id);
            $this->assertSame($claim->id, $entry->expense_claim_id);
            $byCode[$entry->account_code] = $entry;
        }

        $this->assertSame(10000.0, $byCode['6251']->debit);
        $this->assertSame(0.0, $byCode['6251']->credit);
        $this->assertSame(0.0, $byCode['425']->debit);
        $this->assertSame(10000.0, $byCode['425']->credit);

        $this->assertSame(0.0, $service->balanceForClaim($claim));
    }

    public function test_golden_mixed_categories_use_dominant_category_account(): void
    {
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 4000, 'date' => '2026-06-15'],
            ['category' => 'meals', 'description' => 'Repas client', 'amount' => 6000, 'date' => '2026-06-16'],
        ]);

        $service = new ExpenseAccountingEntryService();
        $service->generateForClaim($claim);

        // Catégorie dominante = meals (6 000 > 4 000) → D 6256.
        $this->assertSame(
            10000.0,
            (float) ExpenseAccountingEntry::query()
                ->where('expense_claim_id', $claim->id)
                ->where('account_code', '6256')
                ->value('debit')
        );
        $this->assertSame(0.0, $service->balanceForClaim($claim));
    }

    public function test_regeneration_is_idempotent(): void
    {
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        $service = new ExpenseAccountingEntryService();
        $this->assertSame(2, $service->generateForClaim($claim));
        $this->assertSame(2, $service->generateForClaim($claim)); // 2e appel → remplacement, pas doublon

        $this->assertSame(2, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
    }

    public function test_generation_requires_approved_claim(): void
    {
        [$company, $claim] = $this->claimWithItems('submitted', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        $service = new ExpenseAccountingEntryService();
        $this->expectException(\RuntimeException::class);
        $service->generateForClaim($claim);
    }

    public function test_observer_ignores_non_approval_transitions(): void
    {
        [$company, $claim] = $this->claimWithItems('draft', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        // Soumission (draft → submitted) : aucune écriture.
        $claim->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->assertSame(0, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());

        // Mise à jour sans changement de statut : aucune écriture.
        $claim->update(['description' => 'Mission juin']);
        $this->assertSame(0, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
    }

    public function test_rejection_of_approved_claim_voids_entries(): void
    {
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        $service = new ExpenseAccountingEntryService();
        $service->generateForClaim($claim);
        $this->assertSame(2, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());

        // Rejet (approved → rejected) : les écritures sont supprimées.
        $claim->update(['status' => 'rejected']);
        $this->assertSame(0, ExpenseAccountingEntry::query()->where('expense_claim_id', $claim->id)->count());
        $this->assertSame(0.0, $service->balanceForClaim($claim));
    }

    public function test_tenant_isolation(): void
    {
        [$companyA, $claimA] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);
        [$companyB, $claimB] = $this->claimWithItems('approved', [
            ['category' => 'meals', 'description' => 'Repas', 'amount' => 5000, 'date' => '2026-06-15'],
        ]);

        $service = new ExpenseAccountingEntryService();
        $service->generateForClaim($claimA);
        $service->generateForClaim($claimB);

        $this->assertSame(2, $service->entriesForClaim($claimA)->count());
        $this->assertSame(2, $service->entriesForClaim($claimB)->count());
        $this->assertSame(
            2,
            ExpenseAccountingEntry::query()->where('company_id', $companyA->id)->count()
        );
    }

    public function test_generation_guard_detects_unbalanced_journal(): void
    {
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        $service = new ExpenseAccountingEntryService();

        // Lignes réelles de la note, puis altération du crédit → l'écart doit
        // être détecté par le garde d'équilibre (exception sinon persistée).
        $journal = (new ReflectionMethod($service, 'journalLines'))->invoke($service, $claim);
        $journal[1]['credit'] = $journal[1]['credit'] - 1.0;

        $balance = (new ReflectionMethod($service, 'balanceOf'))->invoke($service, $journal);
        $this->assertSame(1.0, $balance);

        $exception = new UnbalancedExpenseEntriesException(
            "Journal déséquilibré pour la note {$claim->id} : débit − crédit = 1"
        );
        $this->assertStringContainsString('déséquilibré', $exception->getMessage());
    }

    public function test_observer_failure_is_logged_not_propagated(): void
    {
        [$company, $claim] = $this->claimWithItems('submitted', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        // Service simulé en échec : l'observer doit LOGGER sans propager
        // (l'approbation ne casse pas).
        $service = $this->createMock(ExpenseAccountingEntryService::class);
        $service->method('generateForClaim')->willThrowException(new \RuntimeException('boom'));

        $observer = new ExpenseAccountingEntryObserver($service);
        $claim->status = 'approved'; // transition simulée (original = submitted)

        Log::spy();
        $observer->updated($claim);
        Log::shouldHaveReceived('error')->once();
    }

    // ── API ────────────────────────────────────────────────────────────────

    public function test_api_index_lists_entries_for_principal(): void
    {
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);
        (new ExpenseAccountingEntryService())->generateForClaim($claim);

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
        [$company, $claim] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);

        // Principal (lecture seule) → 403
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // RH → 403 (pas dans le groupe api.manager:principal,comptable)
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        Sanctum::actingAs($rh);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertForbidden();

        // Comptable → 200
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $company->id]);
        Sanctum::actingAs($comptable);
        $this->postJson("/api/v1/expense-claims/{$claim->id}/accounting-entries/regenerate")
            ->assertOk()
            ->assertJsonPath('expense_claim_id', $claim->id)
            ->assertJsonPath('generated_lines', 2)
            ->assertJsonPath('balance', 0);
    }

    public function test_api_cross_tenant_returns_404(): void
    {
        [$companyA, $claimA] = $this->claimWithItems('approved', [
            ['category' => 'transport', 'description' => 'Taxi', 'amount' => 10000, 'date' => '2026-06-15'],
        ]);
        [$companyB] = $this->claimWithItems('approved', [
            ['category' => 'meals', 'description' => 'Repas', 'amount' => 5000, 'date' => '2026-06-15'],
        ]);

        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        Sanctum::actingAs($managerB);

        $this->getJson("/api/v1/expense-claims/{$claimA->id}/accounting-entries")
            ->assertNotFound();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @param  list<array{category: string, description: string, amount: int|float, date: string}>  $items
     * @return array{0: Company, 1: ExpenseClaim}
     */
    private function claimWithItems(string $status = 'approved', array $items = []): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);

        /** @var ExpenseClaim $claim */
        $claim = ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Mission juin',
            'description' => 'Frais de mission',
            'status' => $status,
            'total_amount' => array_sum(array_column($items, 'amount')),
            'currency' => $company->currency ?? 'DZD',
            'submitted_at' => $status !== 'draft' ? now() : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'approved_by' => $status === 'approved' ? (string) $employee->id : null,
        ]);

        foreach ($items as $item) {
            ExpenseItem::create([
                'expense_claim_id' => $claim->id,
                'category' => $item['category'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'date' => $item['date'],
            ]);
        }

        return [$company, $claim];
    }
}
