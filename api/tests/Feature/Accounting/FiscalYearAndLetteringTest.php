<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Exceptions\FiscalYearAlreadyClosedException;
use App\Modules\Accounting\Domain\Exceptions\InvalidLetteringException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedLetteringException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingFiscalYear;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Services\ChartOfAccountsService;
use App\Modules\Accounting\Infrastructure\Services\FiscalYearClosingService;
use App\Modules\Accounting\Infrastructure\Services\LetteringService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Clôture d'exercice (report à nouveau) + lettrage comptable — issue #5422.
 *
 * Couvre : ouverture/clôture d'exercice (résultat net, écriture de report
 * à nouveau, périodes figées), gardes de re-clôture, lettrage équilibré /
 * déséquilibré / multi-comptes, délettrage, isolation tenant.
 */
class FiscalYearAndLetteringTest extends TestCase
{
    use RefreshTenantDatabase;

    private int $sourceCounter = 0;



    private function company(string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $country === 'MA' ? 'MAD' : 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function forgetCompany(): void
    {
        app()->forgetInstance('current_company');
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    /**
     * Crée une écriture du journal directement (hors JournalPostingService)
     * — respecte la contrainte débit OU crédit exclusif de la table.
     */
    private function entry(
        Company $company,
        string $accountCode,
        float $amount,
        bool $debit,
        string $date = '2026-06-15',
    ): AccountingJournalEntry {
        $this->sourceCounter++;

        /** @var AccountingJournalEntry $entry */
        $entry = AccountingJournalEntry::create([
            'company_id' => $company->id,
            'entry_date' => $date,
            'period' => substr($date, 0, 7),
            'source_type' => 'manual',
            'source_id' => $this->sourceCounter,
            'account_code' => $accountCode,
            'account_label' => 'Compte '.$accountCode,
            'debit' => $debit ? $amount : 0.0,
            'credit' => $debit ? 0.0 : $amount,
            'piece' => 'TST-'.$this->sourceCounter,
            'description' => 'Écriture de test #'.$this->sourceCounter,
        ]);

        return $entry;
    }

    // ── Clôture d'exercice ───────────────────────────────────────────────────

    public function test_fiscal_year_open_then_close_posts_carry_forward_and_closes_periods(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Plan comptable provisionné → résolution du type par le plan
        // (70 → revenue, 641 → expense, 4457/411/512 → hors résultat).
        app(ChartOfAccountsService::class)->ensureProvisioned($company->id);

        // Écritures 2026 : facture (411 D 1 190 / 4457 C 190 / 70 C 1 000) +
        // charge payée (641 D 400 / 512 C 400) → résultat attendu 600.
        $this->entry($company, '411', 1190.0, true, '2026-05-05');
        $this->entry($company, '4457', 190.0, false, '2026-05-05');
        $this->entry($company, '70', 1000.0, false, '2026-05-05');
        $this->entry($company, '641', 400.0, true, '2026-05-20');
        $this->entry($company, '512', 400.0, false, '2026-05-20');

        // API : ouverture de l'exercice.
        $this->forgetCompany();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $store = $this->postJson('/api/v1/accounting/fiscal-years', ['year' => 2026]);
        $store->assertCreated();
        $store->assertJsonPath('data.year', 2026);
        $store->assertJsonPath('data.status', 'open');

        $index = $this->getJson('/api/v1/accounting/fiscal-years');
        $index->assertOk();
        $index->assertJsonPath('meta.count', 1);
        $index->assertJsonPath('data.0.year', 2026);
        $index->assertJsonPath('data.0.status', 'open');

        // API : clôture → résultat 600, 2 lignes de report, 12 périodes figées.
        $close = $this->postJson('/api/v1/accounting/fiscal-years/2026/close');
        $close->assertOk();
        $close->assertJsonPath('data.year', 2026);
        $close->assertJsonPath('data.status', 'closed');
        $close->assertJsonPath('data.result', 600);
        $close->assertJsonPath('data.entry_count', 2);
        $close->assertJsonPath('data.closed_periods', 12);

        // Exercice passé en closed avec trace d'audit.
        /** @var AccountingFiscalYear $fiscalYear */
        $fiscalYear = AccountingFiscalYear::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('year', 2026)
            ->firstOrFail();
        $this->assertSame('closed', $fiscalYear->status);
        $this->assertNotNull($fiscalYear->closed_at);
        $this->assertSame(trim($manager->first_name.' '.$manager->last_name), $fiscalYear->closed_by);

        // Les 12 périodes de 2026 sont clôturées.
        $periods = AccountingClosedPeriod::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('period')
            ->get();
        $this->assertCount(12, $periods);
        $this->assertSame('2026-01', $periods->first()?->period);
        $this->assertSame('2026-12', $periods->last()?->period);

        // Écriture de report à nouveau : D 12 600 / C 891 600 (bénéfice).
        $closing = AccountingJournalEntry::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('source_type', 'fiscal_year_close')
            ->get();
        $this->assertCount(2, $closing);
        $this->assertSame('CLO-2026', $closing->first()?->piece);
        $this->assertSame('2026-12', $closing->first()?->period);
        $this->assertSame(600.0, (float) $closing->firstWhere('account_code', '12')?->debit);
        $this->assertSame(600.0, (float) $closing->firstWhere('account_code', '891')?->credit);
    }

    public function test_closing_an_already_closed_year_returns_422(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $service = app(FiscalYearClosingService::class);
        $service->open($company->id, 2025, 'system');
        $service->close($company->id, 2025, 'system');

        // Le service refuse la re-clôture (exercice inexistant ou fermé).
        try {
            $service->close($company->id, 2025, 'system');
            $this->fail('La re-clôture doit lever FiscalYearAlreadyClosedException.');
        } catch (FiscalYearAlreadyClosedException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertSame('FISCAL_YEAR_ALREADY_CLOSED', $exception->errorCode());
        }

        // L'API répond 422 avec le code métier.
        $this->forgetCompany();
        Sanctum::actingAs($this->manager($company));
        $response = $this->postJson('/api/v1/accounting/fiscal-years/2025/close');
        $response->assertStatus(422);
        $response->assertJsonPath('code', 'FISCAL_YEAR_ALREADY_CLOSED');
        $this->assertIsString($response->json('message'));

        // Aucune écriture de clôture dupliquée (l'exercice 2025 sans
        // écritures n'a produit aucun report).
        $this->assertSame(0, AccountingJournalEntry::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('source_type', 'fiscal_year_close')
            ->count());
    }

    public function test_closing_a_year_without_entries_results_zero(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $service = app(FiscalYearClosingService::class);
        $service->open($company->id, 2024, 'system');

        $result = $service->close($company->id, 2024, 'system');

        $this->assertSame(0.0, $result['result']);
        $this->assertSame(0, $result['entry_count']);
        $this->assertSame(12, $result['closed_periods']);

        /** @var AccountingFiscalYear $fiscalYear */
        $fiscalYear = AccountingFiscalYear::query()
            ->where('company_id', $company->id)
            ->where('year', 2024)
            ->firstOrFail();
        $this->assertSame('closed', $fiscalYear->status);
        $this->assertSame(0, AccountingJournalEntry::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'fiscal_year_close')
            ->count());
    }

    // ── Lettrage ─────────────────────────────────────────────────────────────

    public function test_lettering_balanced_lines_of_same_account_sets_letter(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $debit = $this->entry($company, '411', 1000.0, true);
        $credit = $this->entry($company, '411', 1000.0, false);

        // Service : lettre posée sur les deux lignes.
        $result = app(LetteringService::class)->letter($company->id, 'L2026-001', [$debit->id, $credit->id]);
        $this->assertSame('L2026-001', $result['letter']);
        $this->assertSame(2, $result['count']);
        $this->assertSame('411', $result['account_code']);

        $this->assertSame('L2026-001', (string) $debit->fresh()?->getAttribute('letter'));
        $this->assertNotNull($debit->fresh()?->getAttribute('lettered_at'));
        $this->assertSame('L2026-001', (string) $credit->fresh()?->getAttribute('letter'));
        $this->assertNotNull($credit->fresh()?->getAttribute('lettered_at'));

        // API : 201 + payload conforme (même lettre → re-lettrage idempotent).
        $this->forgetCompany();
        Sanctum::actingAs($this->manager($company));
        $response = $this->postJson('/api/v1/accounting/journal/lettering', [
            'letter' => 'L2026-001',
            'entry_ids' => [$debit->id, $credit->id],
        ]);
        $response->assertCreated();
        $response->assertJsonPath('data.letter', 'L2026-001');
        $response->assertJsonPath('data.count', 2);
        $response->assertJsonPath('data.account_code', '411');
    }

    public function test_unbalanced_lettering_returns_422(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $debit = $this->entry($company, '411', 1000.0, true);
        $credit = $this->entry($company, '411', 999.0, false);

        try {
            app(LetteringService::class)->letter($company->id, 'L2026-003', [$debit->id, $credit->id]);
            $this->fail('Un lettrage déséquilibré doit lever UnbalancedLetteringException.');
        } catch (UnbalancedLetteringException $exception) {
            $this->assertSame('LETTERING_UNBALANCED', $exception->errorCode());
        }

        $this->forgetCompany();
        Sanctum::actingAs($this->manager($company));
        $response = $this->postJson('/api/v1/accounting/journal/lettering', [
            'letter' => 'L2026-003',
            'entry_ids' => [$debit->id, $credit->id],
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('code', 'LETTERING_UNBALANCED');
        $this->assertIsString($response->json('message'));

        // Aucune lettre posée en base.
        $this->assertNull($debit->fresh()?->getAttribute('letter'));
        $this->assertNull($credit->fresh()?->getAttribute('letter'));
    }

    public function test_lettering_entries_from_different_accounts_returns_422(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $entryA = $this->entry($company, '411', 1000.0, true);
        $entryB = $this->entry($company, '512', 1000.0, false);

        try {
            app(LetteringService::class)->letter($company->id, 'L2026-004', [$entryA->id, $entryB->id]);
            $this->fail('Des écritures de comptes différents doivent lever InvalidLetteringException.');
        } catch (InvalidLetteringException $exception) {
            $this->assertSame('LETTERING_INVALID', $exception->errorCode());
        }

        $this->forgetCompany();
        Sanctum::actingAs($this->manager($company));
        $response = $this->postJson('/api/v1/accounting/journal/lettering', [
            'letter' => 'L2026-004',
            'entry_ids' => [$entryA->id, $entryB->id],
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('code', 'LETTERING_INVALID');
    }

    public function test_unletter_clears_letter_and_returns_204(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $debit = $this->entry($company, '411', 500.0, true);
        $credit = $this->entry($company, '411', 500.0, false);

        app(LetteringService::class)->letter($company->id, 'L2026-005', [$debit->id, $credit->id]);

        // Service : nombre d'écritures délettrées.
        $this->assertSame(2, app(LetteringService::class)->unletter($company->id, 'L2026-005'));

        // Re-lettrage puis délettrage via l'API → 204 et lettre retirée.
        app(LetteringService::class)->letter($company->id, 'L2026-005', [$debit->id, $credit->id]);
        $this->forgetCompany();
        Sanctum::actingAs($this->manager($company));
        $this->deleteJson('/api/v1/accounting/journal/lettering/L2026-005')->assertStatus(204);

        $this->assertNull($debit->fresh()?->getAttribute('letter'));
        $this->assertNull($debit->fresh()?->getAttribute('lettered_at'));
        $this->assertNull($credit->fresh()?->getAttribute('letter'));
        $this->assertNull($credit->fresh()?->getAttribute('lettered_at'));
    }

    // ── Isolation tenant ─────────────────────────────────────────────────────

    public function test_fiscal_year_and_lettering_are_tenant_scoped(): void
    {
        $companyA = $this->company();
        $this->bindCompany($companyA);

        // A : exercice 2026 avec produits/charges, clôturé ; deux lignes lettrées.
        app(FiscalYearClosingService::class)->open($companyA->id, 2026, 'system');
        $this->entry($companyA, '70', 500.0, false, '2026-03-10');
        $this->entry($companyA, '641', 200.0, true, '2026-03-11');
        app(FiscalYearClosingService::class)->close($companyA->id, 2026, 'system');

        $letteredDebit = $this->entry($companyA, '411', 300.0, true);
        $letteredCredit = $this->entry($companyA, '411', 300.0, false);
        app(LetteringService::class)->letter($companyA->id, 'LA-1', [$letteredDebit->id, $letteredCredit->id]);

        $this->assertSame(1, app(FiscalYearClosingService::class)->list($companyA->id)->count());
        $this->assertSame(12, AccountingClosedPeriod::query()->where('company_id', $companyA->id)->count());

        // B : ne voit rien de A.
        $companyB = $this->company('MA');
        $this->forgetCompany();
        $this->bindCompany($companyB);

        $this->assertTrue(app(FiscalYearClosingService::class)->list($companyB->id)->isEmpty());
        $this->assertSame(0, AccountingClosedPeriod::query()->count());
        $this->assertSame(0, AccountingJournalEntry::query()->count());

        // B clôture son propre exercice 2025 (sans écritures) : résultat 0.
        $service = app(FiscalYearClosingService::class);
        $service->open($companyB->id, 2025, 'system');
        $result = $service->close($companyB->id, 2025, 'system');
        $this->assertSame(0.0, $result['result']);
        $this->assertSame(12, $result['closed_periods']);

        // B ne peut pas lettrer des écritures de A (invisibles → invalide).
        Sanctum::actingAs($this->manager($companyB));
        $response = $this->postJson('/api/v1/accounting/journal/lettering', [
            'letter' => 'LB-1',
            'entry_ids' => [$letteredDebit->id, $letteredCredit->id],
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('code', 'LETTERING_INVALID');

        // Intégrité de A préservée : lettre, périodes, données de B.
        $this->assertSame('LA-1', (string) AccountingJournalEntry::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyA->id)
            ->where('id', $letteredDebit->id)
            ->value('letter'));
        $this->assertSame(12, AccountingClosedPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyA->id)
            ->count());
        $this->assertSame(12, AccountingClosedPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyB->id)
            ->count());
    }
}
