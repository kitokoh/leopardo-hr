<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\ManualJournalEntryService;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingJournal;
use App\Modules\Accounting\Domain\Models\AccountingManualEntry;
use App\Modules\Accounting\Infrastructure\Services\JournalResolver;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Saisie d'écriture libre — journal d'opérations diverses (issue #9369).
 *
 * Le module n'offrait aucune saisie manuelle : `/journal` ne proposait que la
 * lecture, l'export CSV et la clôture. Impossible donc de passer une écriture
 * d'ouverture, une régularisation de TVA, une charge à payer (408), une
 * dotation, un virement interne 512 ↔ 53 ou une correction d'erreur.
 *
 * Les assertions portent sur des montants calculés à la main et sur les codes
 * HTTP, pas sur la forme de l'implémentation.
 */
class ManualJournalEntryTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ', string $currency = 'DZD'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);

        return $company;
    }

    /**
     * Plan comptable minimal du tenant (les écritures manuelles sont validées
     * contre lui, contrairement au posting dérivé).
     *
     * @param  array<string, string>  $accounts  code => libellé
     */
    private function chart(Company $company, array $accounts = []): void
    {
        $accounts = $accounts !== [] ? $accounts : [
            '512' => 'Banques',
            '53' => 'Caisse',
            '408' => 'Fournisseurs — factures non parvenues',
            '613' => 'Locations',
            '44571' => 'TVA collectée',
            '411' => 'Clients',
        ];

        foreach ($accounts as $code => $label) {
            // PHP convertit les clés numériques de tableau en entiers : on
            // repasse par une chaîne pour le test de classe comptable.
            $code = (string) $code;

            AccountingChartAccount::create([
                'company_id' => $company->id,
                'code' => $code,
                'label' => $label,
                // Classe 4 = tiers/trésorerie, classe 6 = charge.
                'type' => str_starts_with($code, '4') || str_starts_with($code, '5') ? 'asset' : 'expense',
                'is_active' => true,
            ]);
        }
    }

    private function contract(Company $company, string $role = 'manager'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $employee;
    }

    /** @return list<array{account_code: string, debit: float, credit: float}> */
    private function linesOf(string $manualRef): array
    {
        return AccountingJournalEntry::query()
            ->where('source_type', 'manual')
            ->where('manual_ref', $manualRef)
            ->orderBy('line_no')
            ->get()
            ->map(static fn (AccountingJournalEntry $entry): array => [
                'account_code' => $entry->account_code,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
            ])
            ->all();
    }

    // ── Création ─────────────────────────────────────────────────────────

    public function test_creates_a_balanced_two_line_entry(): void
    {
        $company = $this->company();
        $this->chart($company);

        $result = app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'description' => 'Virement interne banque → caisse',
            'lines' => [
                ['account_code' => '53', 'debit' => 2500, 'credit' => 0],
                ['account_code' => '512', 'debit' => 0, 'credit' => 2500],
            ],
        ]);

        $this->assertSame(2, $result['lines']);

        $lines = $this->linesOf($result['manual_ref']);
        $this->assertSame('53', $lines[0]['account_code']);
        $this->assertSame(2500.0, $lines[0]['debit']);
        $this->assertSame('512', $lines[1]['account_code']);
        $this->assertSame(2500.0, $lines[1]['credit']);
    }

    public function test_accepts_two_lines_on_the_same_account(): void
    {
        // Cas impossible avant #9369 : la contrainte d'idempotence du posting
        // dérivé (company, source, source_id, account_code) interdisait deux
        // lignes sur le même compte dans une même écriture.
        $company = $this->company();
        $this->chart($company);

        $result = app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-16',
            'lines' => [
                ['account_code' => '613', 'debit' => 120],
                ['account_code' => '613', 'debit' => 80],
                ['account_code' => '512', 'credit' => 200],
            ],
        ]);

        $lines = $this->linesOf($result['manual_ref']);
        $this->assertCount(3, $lines);
        $this->assertSame(['613', '613', '512'], array_column($lines, 'account_code'));
    }

    public function test_rejects_an_unbalanced_entry(): void
    {
        $company = $this->company();
        $this->chart($company);

        $this->expectException(UnbalancedJournalEntryException::class);

        app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 1000],
                ['account_code' => '512', 'credit' => 999.99],
            ],
        ]);
    }

    public function test_rejects_an_unknown_account(): void
    {
        $company = $this->company();
        $this->chart($company);

        $this->expectException(DocumentWorkflowException::class);

        app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '999999', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);
    }

    public function test_rejects_an_inactive_account(): void
    {
        $company = $this->company();
        $this->chart($company);

        AccountingChartAccount::query()
            ->where('company_id', $company->id)
            ->where('code', '53')
            ->update(['is_active' => false]);

        $this->expectException(DocumentWorkflowException::class);

        app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '53', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);
    }

    public function test_rejects_a_line_with_both_debit_and_credit(): void
    {
        $company = $this->company();
        $this->chart($company);

        $this->expectException(DocumentWorkflowException::class);

        app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 100, 'credit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);
    }

    public function test_rejects_an_entry_in_a_closed_period(): void
    {
        $company = $this->company();
        $this->chart($company);

        AccountingClosedPeriod::create([
            'company_id' => $company->id,
            'period' => '2026-08',
            'closed_by' => 'comptable@exemple.dz',
        ]);

        $this->expectException(PeriodClosedException::class);

        app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);
    }

    // ── Modification et suppression ──────────────────────────────────────

    public function test_update_replaces_every_line_and_keeps_the_entry_balanced(): void
    {
        $company = $this->company();
        $this->chart($company);

        $service = app(ManualJournalEntryService::class);
        $created = $service->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);

        /** @var AccountingManualEntry $manual */
        $manual = AccountingManualEntry::query()->where('manual_ref', $created['manual_ref'])->firstOrFail();

        $service->update($manual, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 300],
                ['account_code' => '408', 'credit' => 300],
            ],
        ]);

        $lines = $this->linesOf($created['manual_ref']);
        $this->assertCount(2, $lines);
        $this->assertSame(300.0, $lines[0]['debit']);
        $this->assertSame('408', $lines[1]['account_code']);
    }

    public function test_delete_removes_header_and_lines(): void
    {
        $company = $this->company();
        $this->chart($company);

        $service = app(ManualJournalEntryService::class);
        $created = $service->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);

        /** @var AccountingManualEntry $manual */
        $manual = AccountingManualEntry::query()->where('manual_ref', $created['manual_ref'])->firstOrFail();

        $service->delete($manual);

        $this->assertSame([], $this->linesOf($created['manual_ref']));
        $this->assertSame(0, AccountingManualEntry::query()->where('manual_ref', $created['manual_ref'])->count());
    }

    // ── Journal et intégration aux états ─────────────────────────────────

    public function test_entry_lands_on_the_misc_journal_not_on_sales(): void
    {
        $company = $this->company();
        $this->chart($company);

        $result = app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);

        $codes = AccountingJournalEntry::query()
            ->where('manual_ref', $result['manual_ref'])
            ->pluck('journal_code')
            ->unique()
            ->all();

        // Un OD ne doit jamais atterrir sur le journal des ventes : il fausserait
        // le chiffre d'affaires déclaré.
        $this->assertSame(['OD'], $codes);
    }

    public function test_entry_can_target_a_specific_journal(): void
    {
        $company = $this->company();
        $this->chart($company);

        app(JournalResolver::class)->seedDefaults((string) $company->id, 'DZ', \App\Modules\Accounting\Infrastructure\Services\ChartAccountResolver::forCompany($company));

        $result = app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'journal_code' => 'BQ',
            'lines' => [
                ['account_code' => '613', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);

        $this->assertSame(
            ['BQ'],
            AccountingJournalEntry::query()->where('manual_ref', $result['manual_ref'])->pluck('journal_code')->unique()->all(),
        );
    }

    public function test_manager_creates_a_manual_entry_through_the_api(): void
    {
        $company = $this->company();
        $this->chart($company);

        $manager = $this->contract($company);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/accounting/journal/entries', [
            'entry_date' => '2026-08-15',
            'description' => 'Charge à payer — loyer août',
            'lines' => [
                ['account_code' => '613', 'debit' => 1500],
                ['account_code' => '408', 'credit' => 1500],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.lines', 2);
        $this->assertStringStartsWith('OD-202608-', (string) $response->json('data.piece'));
    }

    public function test_api_rejects_an_unbalanced_entry_with_422(): void
    {
        $company = $this->company();
        $this->chart($company);

        Sanctum::actingAs($this->contract($company));

        $response = $this->postJson('/api/v1/accounting/journal/entries', [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 1000],
                ['account_code' => '512', 'credit' => 900],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'UNBALANCED_ENTRY');
    }

    public function test_api_rejects_a_single_line_entry_with_422(): void
    {
        $company = $this->company();
        $this->chart($company);

        Sanctum::actingAs($this->contract($company));

        $response = $this->postJson('/api/v1/accounting/journal/entries', [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 1000],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_api_returns_409_for_a_closed_period(): void
    {
        $company = $this->company();
        $this->chart($company);

        AccountingClosedPeriod::create([
            'company_id' => $company->id,
            'period' => '2026-08',
            'closed_by' => 'comptable@exemple.dz',
        ]);

        Sanctum::actingAs($this->contract($company));

        $response = $this->postJson('/api/v1/accounting/journal/entries', [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '613', 'debit' => 100],
                ['account_code' => '512', 'credit' => 100],
            ],
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'PERIOD_CLOSED');
    }

    public function test_manual_entry_has_no_effect_on_a_document_posting(): void
    {
        // Garde de non-régression : une écriture manuelle ne doit pas empiéter
        // sur l'idempotence du posting dérivé (même compte, même période).
        $company = $this->company();
        $this->chart($company);

        app(ManualJournalEntryService::class)->create((string) $company->id, [
            'entry_date' => '2026-08-15',
            'lines' => [
                ['account_code' => '512', 'debit' => 500],
                ['account_code' => '613', 'credit' => 500],
            ],
        ]);

        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => 'client9389@exemple.dz',
        ]);

        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-2026-9389',
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-20',
            'currency' => 'DZD',
            'subtotal_ht' => 1000.0,
            'tax_amount' => 190.0,
            'total_ttc' => 1190.0,
            'tva_rate' => 19.0,
        ]);

        $posted = app(\App\Modules\Accounting\Infrastructure\Services\JournalPostingService::class)->postDocument($document);

        $this->assertSame(3, $posted);
        // 2 lignes manuelles + 3 lignes de facture = 5.
        $this->assertSame(
            5,
            AccountingJournalEntry::query()->where('company_id', $company->id)->where('period', '2026-08')->count(),
        );
    }
}
