<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Exceptions\CurrencyRateUnavailableException;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Exports\FecExporter;
use App\Modules\Accounting\Infrastructure\Services\FinancialStatementService;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use App\Modules\Accounting\Infrastructure\Services\VatDeclarationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9373 — multi-devises non converti à l'écriture.
 *
 * Le défaut audité : la devise et le taux vivaient sur le **document**, jamais
 * sur l'**écriture**. Un tenant algérien (DZD) qui facturait 1 000 EUR
 * comptabilisait 1 000 DZD — perte de change de 100 %, bilan faux.
 *
 * Les montants attendus sont calculés à la main dans les commentaires de chaque
 * test, jamais repris de l'implémentation.
 */
class MultiCurrencyPostingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $currency = 'DZD', string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['currency' => $currency, 'country' => $country]);

        // Le paramétrage comptable porte la devise de référence du tenant.
        AccountingSettings::query()->create([
            'company_id' => $company->id,
            'currency' => $currency,
            'document_language' => 'fr',
            'number_series' => [],
        ]);

        return $company;
    }

    private function contact(Company $company, string $type = 'customer'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => $type,
            'name' => 'Tiers '.uniqid(),
            'email' => strtolower($type).'-'.uniqid().'@exemple.dz',
        ]);

        return $contact;
    }

    private function invoice(
        Company $company,
        string $type = 'invoice',
        float $ht = 1000.0,
        float $tvaRate = 19.0,
        ?string $currency = null,
        ?float $rate = null,
    ): AccountingDocument {
        $tax = round($ht * $tvaRate / 100, 2);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => strtoupper($type).'-'.uniqid(),
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $this->contact($company, str_starts_with($type, 'purchase') ? 'supplier' : 'customer')->id,
            'issue_date' => '2026-08-05',
            'currency' => $currency,
            'exchange_rate' => $rate,
            'subtotal_ht' => $ht,
            'tax_amount' => $tax,
            'total_ttc' => round($ht + $tax, 2),
            'tva_rate' => $tvaRate,
        ]);

        return $document;
    }

    /** @return array<string, array{debit: float, credit: float, currency: ?string, amount_currency: ?string, exchange_rate: float}> */
    private function linesOf(AccountingDocument $document): array
    {
        /** @var array<string, array{debit: float, credit: float, currency: ?string, amount_currency: ?string, exchange_rate: float}> $lines */
        $lines = [];

        // Boucle explicite (et non `->each(closure)`) : PHPStan ne voit pas les
        // écritures faites par référence dans une closure et croirait `$lines`
        // toujours vide, d'où des « Offset '411' does not exist ».
        foreach (AccountingJournalEntry::query()
            ->where('source_type', 'document')
            ->where('source_id', $document->id)
            ->get() as $entry) {
            $lines[(string) $entry->account_code] = [
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'currency' => $entry->currency,
                'amount_currency' => $entry->amount_currency,
                'exchange_rate' => (float) $entry->exchange_rate,
            ];
        }

        return $lines;
    }

    /**
     * Ligne d'écriture d'un compte, ou zéro si le compte n'a pas été mouvementé.
     *
     * @param  array<string, array{debit: float, credit: float, currency: ?string, amount_currency: ?string, exchange_rate: float}>  $lines
     * @return array{debit: float, credit: float, currency: ?string, amount_currency: ?string, exchange_rate: float}
     */
    private function line(array $lines, string $code): array
    {
        return $lines[$code] ?? [
            'debit' => 0.0,
            'credit' => 0.0,
            'currency' => null,
            'amount_currency' => null,
            'exchange_rate' => 0.0,
        ];
    }

    /**
     * Critère d'acceptation principal : document 1 000 EUR sur un tenant DZD au
     * taux 150 → écritures à 150 000 DZD.
     *
     * Calcul manuel : HT 1 000 EUR + TVA 19 % (190 EUR) = TTC 1 190 EUR.
     *   × 150 → HT 150 000 DZD, TVA 28 500 DZD, TTC 178 500 DZD.
     *   D 411 Clients            178 500
     *   C 706 Ventes             150 000
     *   C 4457 TVA collectée      28 500
     */
    public function test_foreign_currency_invoice_is_converted_to_the_reference_currency(): void
    {
        $company = $this->company('DZD');
        $invoice = $this->invoice($company, 'invoice', 1000.0, 19.0, 'EUR', 150.0);

        app(JournalPostingService::class)->postDocument($invoice);

        $lines = $this->linesOf($invoice);

        $this->assertSame(178500.0, $this->line($lines, '411')['debit']);
        $this->assertSame(150000.0, $this->line($lines, '706')['credit']);
        $this->assertSame(28500.0, $this->line($lines, '4457')['credit']);

        // Traçabilité : devise de comptabilisation, devise d'origine, taux.
        foreach ($lines as $line) {
            $this->assertSame('DZD', $line['currency']);
            $this->assertSame('EUR', $line['amount_currency']);
            $this->assertSame(150.0, $line['exchange_rate']);
        }

        $this->assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
    }

    /**
     * Critère : devises identiques → taux 1, aucune régression (les documents
     * existants des tenants mono-devises restent inchangés).
     */
    public function test_same_currency_document_keeps_todays_amounts(): void
    {
        $company = $this->company('DZD');
        $invoice = $this->invoice($company, 'invoice', 1000.0, 19.0, 'DZD', null);

        app(JournalPostingService::class)->postDocument($invoice);

        $lines = $this->linesOf($invoice);

        $this->assertSame(1190.0, $this->line($lines, '411')['debit']);
        $this->assertSame(1000.0, $this->line($lines, '706')['credit']);
        $this->assertSame(190.0, $this->line($lines, '4457')['credit']);
        $this->assertSame(1.0, $this->line($lines, '411')['exchange_rate']);
        $this->assertSame('DZD', $this->line($lines, '411')['currency']);
    }

    /**
     * Critère : devises différentes sans taux résoluble → exception, et
     * **aucune** écriture (jamais de taux 1 silencieux).
     */
    public function test_missing_rate_raises_and_posts_nothing(): void
    {
        $company = $this->company('DZD');
        $invoice = $this->invoice($company, 'invoice', 1000.0, 19.0, 'EUR', null);

        try {
            app(JournalPostingService::class)->postDocument($invoice);
            $this->fail('Un taux manquant doit lever CurrencyRateUnavailableException.');
        } catch (CurrencyRateUnavailableException) {
            // comportement attendu
        }

        $this->assertSame(0, AccountingJournalEntry::query()
            ->where('source_type', 'document')
            ->where('source_id', $invoice->id)
            ->count());
    }

    /**
     * Critère : Σ débit = Σ crédit en devise de référence, y compris quand
     * l'arrondi de conversion crée un écart.
     *
     * Calcul manuel : HT 333,33 EUR + TVA 20 % (66,67 EUR) = TTC 400,00 EUR.
     * Taux 1,2345 → HT 411,48 / TVA 82,30 / TTC 493,79 (chaque ligne arrondie
     * séparément : 411,48 + 82,30 = 493,78, soit 0,01 d'écart).
     * L'écart est absorbé sur le tiers : D 411 = 493,78.
     */
    public function test_rounding_difference_is_absorbed_on_the_counterparty_line(): void
    {
        $company = $this->company('DZD');
        $invoice = $this->invoice($company, 'invoice', 333.33, 20.0, 'EUR', 1.2345);

        app(JournalPostingService::class)->postDocument($invoice);

        $lines = $this->linesOf($invoice);

        $debit = round($this->line($lines, '411')['debit'], 2);
        $credit = round($this->line($lines, '706')['credit'] + $this->line($lines, '4457')['credit'], 2);

        $this->assertSame($debit, $credit, 'Écriture déséquilibrée après conversion.');
    }

    /**
     * Le FEC porte la devise de l'écriture, pas celle de la company.
     */
    public function test_fec_uses_the_entry_currency(): void
    {
        $company = $this->company('DZD');
        $invoice = $this->invoice($company, 'invoice', 1000.0, 19.0, 'EUR', 150.0);

        app(JournalPostingService::class)->postDocument($invoice);

        // L'export est demandé avec EUR (devise de la company « historique ») :
        // les lignes doivent néanmoins porter DZD, la devise de comptabilisation.
        $csv = app(FecExporter::class)->export((string) $company->id, '2026-08', 'EUR');

        $this->assertStringContainsString('DZD', $csv);
        $this->assertStringNotContainsString(';EUR;', $csv);
    }

    /**
     * Les états financiers exposent la devise d'agrégation et comptent les
     * écritures écartées : on ne additionne jamais deux devises en silence.
     */
    public function test_balance_sheet_reports_currency_and_unconverted_entries(): void
    {
        $company = $this->company('DZD');
        $invoice = $this->invoice($company, 'invoice', 1000.0, 19.0, 'EUR', 150.0);

        app(JournalPostingService::class)->postDocument($invoice);

        // Une écriture orpheline en EUR (simule un import hors service).
        AccountingJournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_date' => '2026-08-06',
            'period' => '2026-08',
            'currency' => 'EUR',
            'amount_currency' => 'EUR',
            'exchange_rate' => 1,
            'journal_code' => 'OD',
            'line_no' => 1,
            'source_type' => 'manual',
            'source_id' => 99,
            'account_code' => '706',
            'account_label' => 'Prestations de services',
            'debit' => 0.0,
            'credit' => 500.0,
            'piece' => 'OD-X',
        ]);

        $sheet = app(FinancialStatementService::class)->balanceSheet((string) $company->id, 2026);

        $this->assertSame('DZD', $sheet['currency']);
        $this->assertSame(1, $sheet['unconverted_entries']);

        // Le total actif ne doit pas inclure les 500 EUR : il vaut le TTC converti.
        $this->assertSame(178500.0, (float) $sheet['total_actif']);
    }

    /**
     * La déclaration de TVA n'agrège pas des devises hétérogènes.
     */
    public function test_vat_declaration_ignores_documents_in_another_currency(): void
    {
        $company = $this->company('DZD');
        $this->invoice($company, 'invoice', 1000.0, 19.0, 'DZD', null);

        // Facture en EUR : hors assiette de la déclaration DZD.
        AccountingDocument::create([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-EUR-'.uniqid(),
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $this->contact($company)->id,
            'issue_date' => '2026-08-07',
            'currency' => 'EUR',
            'subtotal_ht' => 500.0,
            'tax_amount' => 100.0,
            'total_ttc' => 600.0,
            'tva_rate' => 20.0,
        ]);

        $declaration = app(VatDeclarationService::class)->declaration($company, '2026-08');

        // Seule la facture DZD entre dans l'assiette.
        $this->assertSame(1000.0, $declaration['collected']['base']);
        $this->assertSame(190.0, $declaration['collected']['tax']);
        $this->assertSame(1, $declaration['unconverted_documents']);
    }
}
