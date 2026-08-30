<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\AccountingLedgerService;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\PayrollAccountingEntryService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-007 (#5865) — Golden tests Payroll/Accounting : invariants comptables.
 *
 * Méthodologie (docs/architecture/maturity/GOLDEN_ACCOUNTING_MAT007.md) :
 * chaque valeur attendue est CALCULÉE À LA MAIN (HT + TVA 19 % DZ,
 * équilibre débit = crédit), jamais reprise de l'algorithme. Une divergence
 * = régression de non-régression : montants, arrondis, périodes et écritures
 * doivent rester reproductibles.
 *
 * Plan de comptes PCF/SYSCOHADA simplifié (JournalPostingService, #5234) :
 *   411 Clients · 70 Ventes · 709 Rabais · 4457 TVA · 512 Banques · 53 Caisse.
 */
class GoldenAccountingInvariantsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function contact(Company $company, string $email = 'golden@exemple.dz'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Golden',
            'email' => $email,
        ]);

        return $contact;
    }

    private function invoice(
        Company $company,
        AccountingContact $contact,
        string $number,
        string $date = '2026-08-05',
        float $ht = 1000.0,
        float $tvaRate = 19.0,
    ): AccountingDocument {
        $tax = round($ht * $tvaRate / 100, 2);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice->value,
            'number' => $number,
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $contact->id,
            'issue_date' => $date,
            'currency' => 'DZD',
            'subtotal_ht' => $ht,
            'tax_amount' => $tax,
            'total_ttc' => $ht + $tax,
            'tva_rate' => $tvaRate,
        ]);

        return $document;
    }

    private function payment(
        Company $company,
        AccountingDocument $document,
        float $amount,
        string $method = 'cash',
        string $date = '2026-08-10',
    ): AccountingPayment {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => 'GOLD-PAY-'.$document->number,
            'received_at' => $date,
            'status' => PaymentStatus::Recorded->value,
        ]);

        return $payment;
    }

    public function test_golden_dz_invoice_posting_is_balanced(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);
        $document = $this->invoice($company, $contact, 'FAC-2026-0001');

        $count = (new JournalPostingService)->postDocument($document);

        // Calcul manuel : HT 1 000 + TVA 19 % = 190 → TTC 1 190.
        //   D 411  Clients   1 190
        //   C 70   Ventes    1 000
        //   C 4457 TVA         190
        $this->assertSame(3, $count);

        $entries = AccountingJournalEntry::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'document')
            ->where('source_id', $document->id)
            ->get();

        $this->assertCount(3, $entries);

        $debit = $entries->where('account_code', '411')->first();
        $creditSales = $entries->where('account_code', '70')->first();
        $creditVat = $entries->where('account_code', '4457')->first();

        $this->assertNotNull($debit);
        $this->assertSame(1190.0, $debit->debit);
        $this->assertSame(0.0, $debit->credit);
        $this->assertSame(1000.0, $creditSales->credit);
        $this->assertSame(190.0, $creditVat->credit);

        $this->assertTrue((new JournalPostingService)->isPeriodBalanced('2026-08'));
    }

    public function test_golden_posting_is_reproducible_and_idempotent(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);
        $document = $this->invoice($company, $contact, 'FAC-2026-0002', ht: 2500.0);

        $service = new JournalPostingService;

        // Calcul manuel : D 411 2 975 · C 70 2 500 · C 4457 475.
        $this->assertSame(3, $service->postDocument($document));
        $this->assertSame(3, $service->postDocument($document->fresh()));

        $count = AccountingJournalEntry::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'document')
            ->where('source_id', $document->id)
            ->count();

        // Re-posting = rafraîchissement, jamais de doublon.
        $this->assertSame(3, $count);
    }

    public function test_golden_cash_payment_moves_treasury(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);
        $document = $this->invoice($company, $contact, 'FAC-2026-0003');
        $payment = $this->payment($company, $document, 500.0, PaymentMethod::Cash->value);

        $count = (new JournalPostingService)->postPayment($payment);

        // Calcul manuel : encaissement 500 en espèces.
        //   D 53 Caisse 500 · C 411 Clients 500.
        $this->assertSame(2, $count);

        $this->assertSame(500.0, AccountingJournalEntry::query()
            ->where('company_id', $company->id)
            ->where('account_code', '53')
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->value('debit'));
        $this->assertSame(500.0, AccountingJournalEntry::query()
            ->where('company_id', $company->id)
            ->where('account_code', '411')
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->value('credit'));
    }

    public function test_golden_bank_payment_uses_bank_account(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);
        $document = $this->invoice($company, $contact, 'FAC-2026-0004');
        $payment = $this->payment($company, $document, 1190.0, PaymentMethod::BankTransfer->value);

        $count = (new JournalPostingService)->postPayment($payment);

        // Calcul manuel : virement 1 190 → D 512 Banques / C 411.
        $this->assertSame(2, $count);
        $this->assertSame(1190.0, AccountingJournalEntry::query()
            ->where('company_id', $company->id)
            ->where('account_code', '512')
            ->value('debit'));
    }

    public function test_golden_period_totals_match_hand_calculation(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);

        $invoiceA = $this->invoice($company, $contact, 'FAC-2026-0010', ht: 1000.0);
        $invoiceB = $this->invoice($company, $contact, 'FAC-2026-0011', ht: 2500.0);

        $service = new JournalPostingService;
        $service->postDocument($invoiceA);
        $service->postDocument($invoiceB);
        $service->postPayment($this->payment($company, $invoiceA, 500.0, PaymentMethod::Cash->value));

        $totals = $service->entriesForPeriod('2026-08')
            ->groupBy('account_code')
            ->map(fn ($lines) => [
                'debit' => round($lines->sum('debit'), 2),
                'credit' => round($lines->sum('credit'), 2),
            ]);

        // Calcul manuel :
        //   411 : D 1 190 + D 2 975 = 4 165 · C 500 (encaissement) → solde 3 665
        //   70  : C 1 000 + C 2 500 = 3 500
        //   4457: C 190 + C 475 = 665
        //   53  : D 500
        $this->assertSame(4165.0, $totals['411']['debit']);
        $this->assertSame(500.0, $totals['411']['credit']);
        $this->assertSame(3500.0, $totals['70']['credit']);
        $this->assertSame(665.0, $totals['4457']['credit']);
        $this->assertSame(500.0, $totals['53']['debit']);
        $this->assertTrue($service->isPeriodBalanced('2026-08'));
    }

    public function test_closed_period_freezes_entries(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);
        $document = $this->invoice($company, $contact, 'FAC-2026-0020', date: '2026-08-20');

        $service = new JournalPostingService;
        $service->postDocument($document);
        $this->assertSame(3, AccountingJournalEntry::query()->where('company_id', $company->id)->count());

        $service->closePeriod('2026-08', closedBy: 'golden-test');
        $this->assertTrue($service->isPeriodClosed('2026-08'));
        $this->assertTrue(AccountingClosedPeriod::query()->where('period', '2026-08')->exists());

        // Un document daté dans la période close est refusé, aucune écriture ajoutée.
        $late = $this->invoice($company, $contact, 'FAC-2026-0021', date: '2026-08-28');
        try {
            $service->postDocument($late);
            $this->fail('PeriodClosedException attendue pour une période clôturée.');
        } catch (PeriodClosedException) {
            // attendu — la période est figée.
        }

        $this->assertSame(3, AccountingJournalEntry::query()->where('company_id', $company->id)->count());
    }

    public function test_ledger_running_balance_is_cumulative(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);

        $service = new JournalPostingService;
        $service->postDocument($this->invoice($company, $contact, 'FAC-2026-0030', ht: 1000.0));
        $service->postPayment($this->payment($company, $this->invoice($company, $contact, 'FAC-2026-0031', ht: 500.0), 595.0, PaymentMethod::BankTransfer->value));

        $ledger = new AccountingLedgerService;

        // Solde du compte client en août = débit 1 190 + débit 595 − crédit 595 = 1 190.
        $balance = $ledger->balance($company->id, '2026-08');
        $this->assertTrue($balance['balanced']);
        $this->assertSame(0.0, $balance['totals']['ecart']);

        $client = collect($balance['data'])->firstWhere('account_code', '411');
        $this->assertNotNull($client);
        $this->assertSame(1785.0, $client['total_debit']);
        $this->assertSame(595.0, $client['total_credit']);
        $this->assertSame(1190.0, $client['balance']);
    }

    public function test_golden_payroll_dz_accounting_entries_are_balanced(): void
    {
        [$company, $run] = $this->runWithSlips('DZ', 'validated');

        $service = new PayrollAccountingEntryService(new PayrollAccountingExportService);
        $count = $service->generateForRun($run);

        // Golden DZ (cf. PayrollAccountingEntriesFlowTest, socle #5256) :
        // 2 bulletins × 6 écritures = 12 ; débit = crédit = 138 000 ; balance 0.
        $this->assertSame(12, $count);
        $this->assertSame(0.0, $service->balanceForRun($run));

        $totals = $service->entriesForRun($run)
            ->groupBy('account_code')
            ->map(fn ($lines) => [
                'debit' => $lines->sum('debit'),
                'credit' => $lines->sum('credit'),
            ]);

        $this->assertSame(120000.0, $totals['641']['debit']);
        $this->assertSame(18000.0, $totals['645']['debit']);
        $this->assertSame(100000.0, $totals['421']['credit']);
        $this->assertSame(28000.0, $totals['431']['credit']);
        $this->assertSame(6000.0, $totals['4421']['credit']);
        $this->assertSame(4000.0, $totals['425']['credit']);
    }

    public function test_payroll_calculation_audit_is_immutable_and_append_only(): void
    {
        // Le snapshot de calcul de paie (issue #1874) est une trace immuable :
        // pas de timestamps mis à jour, aucune mise à jour, uniquement des
        // insertions (append-only) — le résultat d'un run reste reproductible.
        $audit = PayrollCalculationAudit::create([
            'correlation_id' => 'golden-correlation-1',
            'company_id' => null,
            'actor_type' => PayrollCalculationAudit::ACTOR_USER,
            'country_code' => 'DZ',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'rules_version' => '1.0.0',
            'input_snapshot' => ['base' => 60000],
            'result_snapshot' => ['net' => 50000],
            'status' => PayrollCalculationAudit::STATUS_SUCCESS,
        ]);

        $this->assertTrue($audit->exists);
        $this->assertNull($audit->updated_at);

        // Re-calcul d'un même run = nouvelle ligne, jamais de mutation.
        PayrollCalculationAudit::create([
            'correlation_id' => 'golden-correlation-1',
            'company_id' => null,
            'actor_type' => PayrollCalculationAudit::ACTOR_USER,
            'country_code' => 'DZ',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'rules_version' => '1.0.0',
            'input_snapshot' => ['base' => 60000],
            'result_snapshot' => ['net' => 50000],
            'status' => PayrollCalculationAudit::STATUS_SUCCESS,
        ]);

        $this->assertSame(2, PayrollCalculationAudit::query()
            ->where('correlation_id', 'golden-correlation-1')
            ->count());
    }

    /**
     * @return array{0: Company, 1: PayrollRun}
     */
    private function runWithSlips(string $country = 'DZ', string $status = 'validated'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'DZD']);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => $country,
            'status' => $status,
        ]);

        foreach (['Jean', 'Marie'] as $first) {
            /** @var \App\Core\Auth\Domain\Models\Employee $employee */
            $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
                'company_id' => $company->id,
                'first_name' => $first,
                'last_name' => 'Golden',
                'matricule' => null,
            ]);

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
                'status' => $status,
            ]);

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations salariales',
                'type' => 'deduction',
                'base_amount' => 60000,
                'rate' => null,
                'amount' => 3000,
                'order' => 1,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'IRG',
                'type' => 'deduction',
                'base_amount' => 60000,
                'rate' => null,
                'amount' => 2000,
                'order' => 2,
            ]);
        }

        return [$company, $run];
    }
}
