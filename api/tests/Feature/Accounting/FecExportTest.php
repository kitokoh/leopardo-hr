<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Export FEC — Fichier des Écritures Comptables (norme DGFiP, issue #5422) :
 * format 13 colonnes, numérotation par pièce, équilibre débit/crédit,
 * neutralisation des formules CSV, période vide/invalide, isolation tenant.
 */
class FecExportTest extends TestCase
{
    use RefreshTenantDatabase;

    /** En-tête FEC officiel — 13 colonnes exactement. */
    private const FEC_HEADER = 'JournalCode;JournalLib;EcritureNum;EcritureDate;CompteNum;CompteLib;PieceRef;PieceDate;Libelle;Debit;Credit;Devise;MontantDevise';

    /** @var list<string> */
    private const FEC_COLUMNS = [
        'JournalCode',
        'JournalLib',
        'EcritureNum',
        'EcritureDate',
        'CompteNum',
        'CompteLib',
        'PieceRef',
        'PieceDate',
        'Libelle',
        'Debit',
        'Credit',
        'Devise',
        'MontantDevise',
    ];

    // ── Helpers (calqués sur AccountingJournalTest) ─────────────────────────

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

    private function contact(Company $company, string $email = 'client@exemple.dz'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => $email,
        ]);

        return $contact;
    }

    private function document(
        Company $company,
        string $type = 'invoice',
        string $status = 'sent',
        string $date = '2026-08-05',
        float $ht = 1000.0,
        float $tax = 190.0,
        string $number = 'FAC-2026-0001',
    ): AccountingDocument {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => $number,
            'status' => $status,
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

    private function payment(Company $company, AccountingDocument $document, float $amount, string $method = 'bank_transfer', string $date = '2026-08-10', string $status = 'recorded', ?string $reference = null): AccountingPayment
    {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'received_at' => $date,
            'status' => $status,
        ]);

        return $payment;
    }

    /** Authentifie un manager (RBAC principal/comptable) du tenant donné. */
    private function actingAsManager(Company $company): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);
    }

    /**
     * Parse le CSV FEC (BOM + en-tête retirés) en lignes associatives
     * indexées par nom de colonne.
     *
     * @return list<array<string, string>>
     */
    private function parseFecRows(string $csv): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n/', $csv);
        if ($lines === false) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = str_getcsv($line, ';');
        }

        // Ligne d'en-tête.
        array_shift($rows);

        $associative = [];
        foreach ($rows as $row) {
            $associative[] = array_combine(self::FEC_COLUMNS, $row) ?: [];
        }

        return $associative;
    }

    /** Montant FEC (« 1234,56 » ou vide) → float. */
    private function parseAmount(string $value): float
    {
        return $value === '' ? 0.0 : (float) str_replace(',', '.', $value);
    }

    // ── Tests ───────────────────────────────────────────────────────────────

    public function test_export_produces_exact_13_column_fec_file(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->forgetCompany();

        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="fec-2026-08.csv"');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(self::FEC_HEADER, $csv);

        foreach (explode("\r\n", ltrim($csv, "\xEF\xBB\xBF")) as $line) {
            if ($line === '') {
                continue;
            }
            $this->assertCount(13, str_getcsv($line, ';'), 'Chaque ligne FEC doit avoir 13 colonnes : '.$line);
        }

        $rows = $this->parseFecRows($csv);
        $this->assertCount(3, $rows);
        // Facture : 411 et 70 → VE ; 4457 (TVA) ni 6xx ni 7xx/411 → OD.
        $this->assertSame(['VE', 'OD'], array_values(array_unique(array_column($rows, 'JournalCode'))));
        $this->assertSame('DZD', $rows[0]['Devise']);
    }

    public function test_lines_of_same_piece_share_sequential_ecriture_num(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoiceA = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, 'FAC-2026-0001');
        $invoiceB = $this->document($company, 'invoice', 'sent', '2026-08-06', 500.0, 95.0, 'FAC-2026-0002');
        app(JournalPostingService::class)->postDocument($invoiceA);
        app(JournalPostingService::class)->postDocument($invoiceB);
        $this->forgetCompany();

        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertOk();

        $rows = $this->parseFecRows($response->streamedContent());
        $this->assertCount(6, $rows);

        // Même EcritureNum pour toutes les lignes d'une même pièce.
        foreach ($rows as $row) {
            if ($row['PieceRef'] === 'FAC-2026-0001') {
                $this->assertSame('1', $row['EcritureNum']);
            }
            if ($row['PieceRef'] === 'FAC-2026-0002') {
                $this->assertSame('2', $row['EcritureNum']);
            }
        }

        // Numérotation séquentielle 1..N (contiguë, triée par pièce/date/id).
        $this->assertSame(['1', '1', '1', '2', '2', '2'], array_column($rows, 'EcritureNum'));

        // Formats français : virgule décimale, cellule vide si zéro.
        $debitRow = $this->firstRowWithCompte($rows, '411', 'FAC-2026-0001');
        $this->assertSame('1190,00', $debitRow['Debit']);
        $this->assertSame('', $debitRow['Credit']);
        $this->assertSame('1190,00', $debitRow['MontantDevise']);
    }

    public function test_period_is_balanced_debit_equals_credit(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->payment($company, $invoice, 500.0, 'bank_transfer', '2026-08-10');
        app(JournalPostingService::class)->postPayment($invoice->payments()->firstOrFail());
        $this->forgetCompany();

        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertOk();

        $rows = $this->parseFecRows($response->streamedContent());

        $debit = 0.0;
        $credit = 0.0;
        foreach ($rows as $row) {
            $debit += $this->parseAmount($row['Debit']);
            $credit += $this->parseAmount($row['Credit']);
            // Chaque ligne est à sens unique : débit OU crédit.
            $this->assertTrue(
                ($row['Debit'] === '') xor ($row['Credit'] === ''),
                'Débit et crédit sont exclusifs sur une ligne FEC : '.json_encode($row),
            );
        }

        $this->assertSame(1690.0, round($debit, 2));
        $this->assertSame(1690.0, round($credit, 2));
        $this->assertEqualsWithDelta(0.0, $debit - $credit, 0.005);
    }

    public function test_formula_injection_cells_are_apostrophe_prefixed(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        // Pièce piégeuse (injection de formule CSV) : doit être neutralisée.
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, '=CMD-2026-01');
        app(JournalPostingService::class)->postDocument($invoice);
        $this->forgetCompany();

        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertOk();

        $rows = $this->parseFecRows($response->streamedContent());
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertStringStartsWith("'=", $row['PieceRef'], 'PieceRef commençant par = doit être préfixée d\'une apostrophe.');
            // Le libellé « Facture =CMD-2026-01 » ne commence pas par = → intact.
            $this->assertSame('Facture =CMD-2026-01', $row['Libelle']);
        }
    }

    public function test_empty_period_returns_422_fec_no_entries(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $this->forgetCompany();

        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertStatus(422);
        $response->assertJson([
            'message' => __('accounting.fec_no_entries'),
            'code' => 'FEC_NO_ENTRIES',
        ]);
    }

    public function test_invalid_period_returns_422(): void
    {
        $company = $this->company();
        $this->actingAsManager($company);

        foreach (['2026-13', '2026-00', '2026-8', '2026/08', 'abc', ''] as $invalidPeriod) {
            $response = $this->get('/api/v1/accounting/journal/export-fec'.($invalidPeriod === '' ? '' : '?period='.$invalidPeriod));
            $response->assertStatus(422);
        }
    }

    public function test_export_is_tenant_isolated(): void
    {
        $companyA = $this->company();
        $this->bindCompany($companyA);
        $invoiceA = $this->document($companyA, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoiceA);
        $this->forgetCompany();

        // Le tenant B ne voit aucune écriture du tenant A → 422 FEC_NO_ENTRIES.
        $companyB = $this->company('MA');
        $this->actingAsManager($companyB);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertStatus(422);
        $response->assertJsonPath('code', 'FEC_NO_ENTRIES');

        // Le tenant A, lui, exporte bien ses écritures.
        $this->actingAsManager($companyA);
        $responseA = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $responseA->assertOk();
        $this->assertCount(3, $this->parseFecRows($responseA->streamedContent()));
    }

    public function test_export_maps_journal_codes_labels_and_amounts(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->payment($company, $invoice, 1190.0, 'bank_transfer', '2026-08-10');
        app(JournalPostingService::class)->postPayment($invoice->payments()->firstOrFail());
        $this->forgetCompany();

        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');
        $response->assertOk();

        $rows = $this->parseFecRows($response->streamedContent());
        $this->assertCount(5, $rows);

        // Facture : 411 et 70 → VE, 4457 → OD (ni 6xx ni 7xx/411).
        foreach ($this->rowsForPiece($rows, 'FAC-2026-0001') as $row) {
            $expectedCode = in_array($row['CompteNum'], ['411', '70'], true) ? 'VE' : 'OD';
            $this->assertSame($expectedCode, $row['JournalCode']);
            $this->assertSame($expectedCode === 'VE' ? 'Journal des ventes' : 'Opérations diverses', $row['JournalLib']);
            $this->assertSame('20260805', $row['EcritureDate']);
            $this->assertSame('20260805', $row['PieceDate']);
        }

        // Paiement : trésorerie.
        foreach ($this->rowsForPiece($rows, 'PAY-') as $row) {
            $this->assertSame('TR', $row['JournalCode']);
            $this->assertSame('Journal de trésorerie', $row['JournalLib']);
            $this->assertSame('20260810', $row['EcritureDate']);
            $this->assertSame('20260810', $row['PieceDate']);
        }

        // MontantDevise = montant (même devise) ; aucune ligne de totaux.
        foreach ($rows as $row) {
            $this->assertSame('DZD', $row['Devise']);
            $this->assertSame($row['Debit'] !== '' ? $row['Debit'] : $row['Credit'], $row['MontantDevise']);
        }
        $this->assertSame([], array_filter($rows, static fn (array $row): bool => $row['JournalCode'] === 'TOTAL'));
    }

    /**
     * Lignes FEC dont la référence de pièce commence par le préfixe donné.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    private function rowsForPiece(array $rows, string $prefix): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => str_starts_with($row['PieceRef'], $prefix),
        ));
    }

    /**
     * Première ligne FEC du compte donné pour la pièce donnée.
     *
     * @param  list<array<string, string>>  $rows
     * @return array<string, string>
     */
    private function firstRowWithCompte(array $rows, string $accountCode, string $piece): array
    {
        foreach ($rows as $row) {
            if ($row['CompteNum'] === $accountCode && $row['PieceRef'] === $piece) {
                return $row;
            }
        }

        $this->fail('Aucune ligne FEC pour le compte '.$accountCode.' de la pièce '.$piece.'.');
    }
}
