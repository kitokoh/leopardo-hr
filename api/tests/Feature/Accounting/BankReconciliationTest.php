<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\BankStatement;
use App\Modules\Accounting\Infrastructure\Services\BankReconciliationService;
use App\Modules\Accounting\Infrastructure\Services\BankStatementImportService;
use App\Modules\Accounting\Infrastructure\Services\PaymentRegistrationService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Rapprochement bancaire Phase D (#5435) — US2 : matching automatique.
 */
class BankReconciliationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function document(Company $company, string $number, float $ttc = 1190.0): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => 'client@exemple.dz',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => $number,
            'status' => 'sent',
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-05',
            'due_date' => '2026-08-20',
            'currency' => 'DZD',
            'subtotal_ht' => 1000.0,
            'tax_amount' => 190.0,
            'total_ttc' => $ttc,
            'tva_rate' => 19.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    private function importCsv(Company $company, string $csv, string $reference = 'RELEVE-1'): BankStatement
    {
        $result = app(BankStatementImportService::class)->import(
            companyId: $company->id,
            statementPeriod: '2026-08',
            importReference: $reference,
            csvContent: $csv,
        );

        return $result['statement'];
    }

    public function test_exact_match_reconciles_payment_and_line(): void
    {
        $company = $this->company();
        app()->instance('current_company', $company);

        $invoice = $this->document($company, 'FAC-2026-0001');
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer', 'VIR-2026-001', Carbon::parse('2026-08-05'));

        $csv = "date;label;amount;reference\n2026-08-05;Paiement facture FAC-2026-0001;1190.00;VIR-2026-001\n";
        $statement = $this->importCsv($company, $csv);

        $result = app(BankReconciliationService::class)->autoReconcile($statement);

        $this->assertSame(1, $result['auto_matched']);
        $this->assertSame(0, $result['pending']);

        $line = $statement->lines()->firstOrFail();
        $this->assertSame('matched', $line->status);
        $this->assertSame($payment->id, $line->matched_payment_id);
        $this->assertSame(100, $line->confidence);

        $payment->refresh();
        $this->assertSame('matched', $payment->status);
        $this->assertNotNull($payment->reconciled_at);

        $statement->refresh();
        $this->assertSame('reconciled', $statement->status);
    }

    public function test_approximate_match_is_proposed_not_auto_matched(): void
    {
        $company = $this->company();
        app()->instance('current_company', $company);

        $invoice = $this->document($company, 'FAC-2026-0001');
        // paiement le 08-07 (2 jours d'écart), référence différente
        app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer', 'REF-INTERNE', Carbon::parse('2026-08-07'));

        $csv = "date;label;amount;reference\n2026-08-05;Paiement facture;1190.00;VIR-2026-001\n";
        $statement = $this->importCsv($company, $csv);

        $result = app(BankReconciliationService::class)->autoReconcile($statement);

        $this->assertSame(0, $result['auto_matched']);
        $this->assertSame(1, $result['proposed']);

        $line = $statement->lines()->firstOrFail();
        $this->assertSame('pending', $line->status);
        $this->assertArrayHasKey('proposed_payment_id', (array) $line->metadata);
        $this->assertSame('reconciling', $statement->refresh()->status);
    }

    public function test_no_candidate_line_stays_pending(): void
    {
        $company = $this->company();
        app()->instance('current_company', $company);

        $csv = "date;label;amount;reference\n2026-08-05;Virement fournisseur;3000.00;VIR-FOURNISSEUR\n";
        $statement = $this->importCsv($company, $csv);

        $result = app(BankReconciliationService::class)->autoReconcile($statement);

        $this->assertSame(0, $result['auto_matched']);
        $this->assertSame(0, $result['proposed']);
        $this->assertSame(1, $result['pending']);

        $line = $statement->lines()->firstOrFail();
        $this->assertSame('pending', $line->status);
        $this->assertSame('imported', $statement->refresh()->status);
    }

    public function test_matching_is_idempotent(): void
    {
        $company = $this->company();
        app()->instance('current_company', $company);

        $invoice = $this->document($company, 'FAC-2026-0001');
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer', 'VIR-2026-001', Carbon::parse('2026-08-05'));

        $csv = "date;label;amount;reference\n2026-08-05;Paiement facture;1190.00;VIR-2026-001\n";
        $statement = $this->importCsv($company, $csv);

        $service = app(BankReconciliationService::class);
        $service->autoReconcile($statement);
        $second = $service->autoReconcile($statement->refresh());

        // rien de nouveau : le paiement est déjà matched (non éligible)
        $this->assertSame(0, $second['auto_matched']);
        $this->assertSame(1, $statement->refresh()->matchedLines()->count());
        $payment->refresh();
        $this->assertSame('matched', $payment->status);
    }


    public function test_export_csv_contains_lines_statuses_and_totals(): void
    {
        $company = $this->company();
        $statement = $this->importCsv(
            $company,
            "date;label;amount;reference\n2026-08-03;Facture client 1;1190.00;FAC-2026-001\n2026-08-10;Virement fournisseur;-450.00;VIR-001\n",
        );

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/accounting/bank-statements/'.$statement->id.'/export')
            ->assertOk();

        $csv = $response->streamedContent();
        // BOM UTF-8 + en-tête.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Ligne;Date;Libelle;Montant;Statut', $csv);
        // Lignes du relevé présentes avec leur statut.
        $this->assertStringContainsString('Facture client 1', $csv);
        $this->assertStringContainsString('Virement fournisseur', $csv);
        $this->assertStringContainsString('pending', $csv);
        // Totaux + écart de clôture.
        $this->assertStringContainsString('TOTAL MATCHED', $csv);
        $this->assertStringContainsString('TOTAL PENDING', $csv);
        $this->assertStringContainsString('ECART CLOTURE', $csv);
        // Header Referrer-Policy no-referrer (#5521).
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function test_export_csv_isolation_tenant(): void
    {
        $company = $this->company();
        $otherCompany = $this->company();
        $statement = $this->importCsv(
            $company,
            "date;label;amount;reference\n2026-08-03;Facture client 1;1190.00;FAC-2026-001\n",
        );

        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($otherManager);

        $this->getJson('/api/v1/accounting/bank-statements/'.$statement->id.'/export')
            ->assertNotFound();
    }
}
