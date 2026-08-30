<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-007 (#5865) — Golden tests comptabilité : invariants reproductibles
 * (BC-01 PLATFORM, invariants Accounting).
 *
 * Complète les suites existantes (équilibre, idempotence, clôture) par des
 * scénarios GOLDEN calculés À LA MAIN :
 *   - facture à TVA réduite 9 % → écritures verrouillées (411/4457/70) ;
 *   - grand-livre de période multi-documents → totaux débit/crédit calculés
 *     à la main, équilibre strict ;
 *   - re-posting → jeu d'écritures IDENTIQUE (reproductibilité).
 *
 * Méthodologie : chaque valeur attendue est calculée dans les commentaires,
 * jamais reprise du service de posting.
 */
class GoldenAccountingJournalTest extends TestCase
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

    private function document(Company $company, string $type, float $ht, float $tax, string $number, string $date = '2026-08-05'): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Golden',
            'email' => 'golden@exemple.dz',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => $number,
            'status' => 'sent',
            'contact_id' => $contact->id,
            'issue_date' => $date,
            'currency' => 'DZD',
            'subtotal_ht' => $ht,
            'tax_amount' => $tax,
            'total_ttc' => $ht + $tax,
            'tva_rate' => $tax > 0 ? round($tax / $ht * 100, 2) : null,
        ]);

        return $document;
    }

    public function test_golden_invoice_with_reduced_vat_posts_hand_computed_lines(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Calcul manuel — facture 1 000,00 DZD HT + TVA réduite 9 % = 90,00 :
        //   débit  client (411)   1 090,00
        //   crédit TVA collectée (4457)      90,00
        //   crédit produits (70)          1 000,00
        $invoice = $this->document($company, 'invoice', 1000.0, 90.0, 'FAC-GOLD-001');

        $count = app(JournalPostingService::class)->postDocument($invoice);

        self::assertSame(3, $count);

        $lines = AccountingJournalEntry::query()
            ->where('source_type', 'document')
            ->where('source_id', $invoice->id)
            ->orderBy('account_code')
            ->get();

        self::assertSame('411', $lines[0]->account_code);
        self::assertSame(1090.0, $lines[0]->debit);
        self::assertSame(0.0, $lines[0]->credit);

        self::assertSame('4457', $lines[1]->account_code);
        self::assertSame(0.0, $lines[1]->debit);
        self::assertSame(90.0, $lines[1]->credit);

        self::assertSame('70', $lines[2]->account_code);
        self::assertSame(0.0, $lines[2]->debit);
        self::assertSame(1000.0, $lines[2]->credit);

        self::assertSame('2026-08', $lines[0]->period);
        self::assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
    }

    public function test_golden_multi_document_period_ledger_totals(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Grand-livre de période calculé À LA MAIN — août 2026 :
        //   Facture A : 1 000 HT + 190 TVA → D 1 190 / C 1 190
        //   Facture B : 2 500 HT + 475 TVA → D 2 975 / C 2 975
        //   Avoir     :   200 HT +  38 TVA → D 238  / C 238 (contre-passation)
        //   Totaux période : débit = 1 190 + 2 975 + 38 + 200 = 4 403
        //                    crédit = 1 190 + 2 975 + 238 = 4 403
        $this->document($company, 'invoice', 1000.0, 190.0, 'FAC-GOLD-002', '2026-08-03');
        $this->document($company, 'invoice', 2500.0, 475.0, 'FAC-GOLD-003', '2026-08-11');
        $this->document($company, 'credit_note', 200.0, 38.0, 'AVR-GOLD-001', '2026-08-19');

        $service = app(JournalPostingService::class);

        foreach (AccountingDocument::query()->get() as $doc) {
            $service->postDocument($doc);
        }

        self::assertTrue($service->isPeriodBalanced('2026-08'));

        $totals = $service->totalsForPeriod('2026-08');
        self::assertSame(4403.0, $totals['debit']);
        self::assertSame(4403.0, $totals['credit']);

        // Chaque ligne individuelle est équilibrée (débit XOR crédit, zéro les deux).
        foreach (AccountingJournalEntry::query()->get() as $entry) {
            self::assertSame(0.0, min($entry->debit, $entry->credit), 'jamais débit+crédit sur la même ligne');
            self::assertNotSame(0.0, max($entry->debit, $entry->credit));
        }
    }

    public function test_golden_reposting_yields_identical_ledger(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $this->document($company, 'invoice', 1000.0, 190.0, 'FAC-GOLD-004');
        $service = app(JournalPostingService::class);

        foreach (AccountingDocument::query()->get() as $doc) {
            $service->postDocument($doc);
        }
        $firstPass = $this->ledgerSnapshot();

        // Reproducibilité : une seconde passe (re-posting) ne change rien.
        foreach (AccountingDocument::query()->get() as $doc) {
            $service->postDocument($doc);
        }

        self::assertSame($firstPass, $this->ledgerSnapshot());
        self::assertSame(3, AccountingJournalEntry::query()->count());
    }

    /** @return list<array{account_code: string, debit: float, credit: float, period: string}> */
    private function ledgerSnapshot(): array
    {
        return AccountingJournalEntry::query()
            ->orderBy('source_type')
            ->orderBy('source_id')
            ->orderBy('account_code')
            ->get()
            ->map(static fn (AccountingJournalEntry $entry): array => [
                'account_code' => $entry->account_code,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
                'period' => $entry->period,
            ])
            ->all();
    }
}
