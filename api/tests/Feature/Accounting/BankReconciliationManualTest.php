<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\BankStatement;
use App\Modules\Accounting\Domain\Models\BankStatementLine;
use App\Modules\Accounting\Infrastructure\Services\BankReconciliationService;
use App\Modules\Accounting\Infrastructure\Services\BankStatementImportService;
use App\Modules\Accounting\Infrastructure\Services\PaymentRegistrationService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Rapprochement bancaire Phase D (#5435) — US3 : matching manuel + lettrage.
 */
class BankReconciliationManualTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    private function document(Company $company, string $number): AccountingDocument
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
            'total_ttc' => 1190.0,
            'tva_rate' => 19.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    private function importCsv(Company $company, string $reference = 'RELEVE-1'): BankStatement
    {
        $csv = "date;label;amount;reference\n2026-08-05;Paiement facture FAC-2026-0001;1190.00;VIR-2026-001\n";

        return app(BankStatementImportService::class)->import(
            companyId: $company->id,
            statementPeriod: '2026-08',
            importReference: $reference,
            csvContent: $csv,
        )['statement'];
    }

    public function test_manual_match_reconciles_and_lettrages(): void
    {
        $company = $this->company();
        $invoice = $this->document($company, 'FAC-2026-0001');
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer', 'VIR-2026-001', Carbon::parse('2026-08-05'));
        $statement = $this->importCsv($company);
        /** @var BankStatementLine $line */
        $line = $statement->lines()->firstOrFail();

        Sanctum::actingAs($this->manager($company));

        $response = $this->postJson("/api/v1/accounting/bank-statement-lines/{$line->id}/match", [
            'payment_id' => $payment->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'matched');
        $response->assertJsonPath('data.matched_payment_id', $payment->id);
        $response->assertJsonPath('data.payment_status', 'matched');
        $this->assertNotNull($response->json('data.reconciled_at'));

        $line->refresh();
        $payment->refresh();
        $this->assertSame('matched', $line->status);
        $this->assertSame($payment->id, $line->matched_payment_id);
        $this->assertSame('matched', $payment->status);
        $this->assertNotNull($payment->reconciled_at);
    }

    public function test_rematch_already_matched_line_returns_409(): void
    {
        $company = $this->company();
        $invoice = $this->document($company, 'FAC-2026-0001');
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer', 'VIR-2026-001', Carbon::parse('2026-08-05'));
        $statement = $this->importCsv($company);
        /** @var BankStatementLine $line */
        $line = $statement->lines()->firstOrFail();

        app(BankReconciliationService::class)
            ->matchManually($line, $payment);

        $otherInvoice = $this->document($company, 'FAC-2026-0002');
        $otherPayment = app(PaymentRegistrationService::class)->register($otherInvoice, 1190.0, 'check', 'CHEQUE-1', Carbon::parse('2026-08-06'));

        Sanctum::actingAs($this->manager($company));

        $response = $this->postJson("/api/v1/accounting/bank-statement-lines/{$line->id}/match", [
            'payment_id' => $otherPayment->id,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'BANK_STATEMENT_LINE_ALREADY_MATCHED');
    }

    public function test_cross_tenant_manual_match_returns_404(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();

        $invoice = $this->document($companyA, 'FAC-2026-0001');
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer', 'VIR-2026-001', Carbon::parse('2026-08-05'));
        $statement = $this->importCsv($companyA, 'RELEVE-A');
        /** @var BankStatementLine $line */
        $line = $statement->lines()->firstOrFail();

        Sanctum::actingAs($this->manager($companyB));

        $response = $this->postJson("/api/v1/accounting/bank-statement-lines/{$line->id}/match", [
            'payment_id' => $payment->id,
        ]);

        // ligne du tenant A résolue sous le scope B → 404 fail-closed
        $response->assertNotFound();
    }
}
