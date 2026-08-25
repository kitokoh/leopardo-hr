<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Journal des écritures comptables (issue #5234) :
 * posting équilibré documents + paiements, idempotence, période/clôture,
 * API (liste, export CSV, clôture, re-posting), isolation tenant.
 */
class AccountingJournalTest extends TestCase
{
    use RefreshTenantDatabase;

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

    /** @return array<int, array{account_code: string, debit: float, credit: float}> */
    private function linesOf(AccountingDocument $document, string $sourceType = 'document'): array
    {
        return AccountingJournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $document->id)
            ->orderBy('account_code')
            ->get()
            ->map(static fn (AccountingJournalEntry $entry): array => [
                'account_code' => $entry->account_code,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
            ])
            ->values()
            ->all();
    }

    // ── US1 : posting des documents ──────────────────────────────────────────

    public function test_invoice_posting_creates_balanced_entries(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);

        $count = app(JournalPostingService::class)->postDocument($invoice);

        $this->assertSame(3, $count);
        $lines = $this->linesOf($invoice);
        $this->assertSame('411', $lines[0]['account_code']);
        $this->assertSame(1190.0, $lines[0]['debit']);
        $this->assertSame('4457', $lines[1]['account_code']);
        $this->assertSame(190.0, $lines[1]['credit']);
        $this->assertSame('70', $lines[2]['account_code']);
        $this->assertSame(1000.0, $lines[2]['credit']);
        // Période dérivée de la date d'émission.
        $this->assertSame('2026-08', AccountingJournalEntry::query()->first()?->period);
        // Invariant : équilibre du journal complet.
        $this->assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
        $totals = app(JournalPostingService::class)->totalsForPeriod('2026-08');
        $this->assertSame(1190.0, $totals['debit']);
        $this->assertSame(1190.0, $totals['credit']);
    }

    public function test_credit_note_posting_reverses_entries(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $creditNote = $this->document($company, 'credit_note', 'sent', '2026-08-06', 100.0, 19.0, 'AVR-2026-0002');

        app(JournalPostingService::class)->postDocument($creditNote);

        $lines = $this->linesOf($creditNote);
        $this->assertSame('411', $lines[0]['account_code']);
        $this->assertSame(119.0, $lines[0]['credit']);
        $this->assertSame('4457', $lines[1]['account_code']);
        $this->assertSame(19.0, $lines[1]['debit']);
        $this->assertSame('709', $lines[2]['account_code']);
        $this->assertSame(100.0, $lines[2]['debit']);
        $this->assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
    }

    public function test_documents_without_accounting_impact_are_not_posted(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        foreach ([DocumentType::Proforma, DocumentType::Quote, DocumentType::DeliveryNote, DocumentType::Receipt] as $i => $type) {
            $doc = $this->document($company, $type->value, 'sent', '2026-08-05', 500.0, 95.0, 'DOC-'.($i + 1));
            $this->assertSame(0, app(JournalPostingService::class)->postDocument($doc), 'pas d\'écriture pour '.$type->value);
        }

        // Brouillon et annulé → jamais passés.
        foreach ([DocumentStatus::Draft, DocumentStatus::Cancelled] as $i => $status) {
            $doc = $this->document($company, 'invoice', $status->value, '2026-08-05', 500.0, 95.0, 'FAC-2026-'.(1000 + $i));
            $this->assertSame(0, app(JournalPostingService::class)->postDocument($doc), 'pas d\'écriture pour statut '.$status->value);
        }

        $this->assertSame(0, AccountingJournalEntry::query()->count());
    }

    // ── US1bis : posting des paiements ───────────────────────────────────────

    public function test_payment_posting_uses_treasury_account_by_method(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);

        $this->payment($company, $invoice, 500.0, PaymentMethod::BankTransfer->value, '2026-08-10');
        $this->payment($company, $invoice, 690.0, PaymentMethod::Cash->value, '2026-08-12');

        $service = app(JournalPostingService::class);
        foreach ($invoice->payments()->get() as $payment) {
            $service->postPayment($payment);
        }

        $bankLines = AccountingJournalEntry::query()
            ->where('source_type', 'payment')
            ->where('account_code', '512')
            ->get();
        $cashLines = AccountingJournalEntry::query()
            ->where('source_type', 'payment')
            ->where('account_code', '53')
            ->get();
        $this->assertCount(1, $bankLines);
        $this->assertSame(500.0, $bankLines->first()?->debit);
        $this->assertCount(1, $cashLines);
        $this->assertSame(690.0, $cashLines->first()?->debit);

        $this->assertTrue($service->isPeriodBalanced('2026-08'));
    }

    public function test_pending_payment_is_not_posted(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05');
        $payment = $this->payment($company, $invoice, 1190.0, 'bank_transfer', '2026-08-10', PaymentStatus::Pending->value);

        $this->assertSame(0, app(JournalPostingService::class)->postPayment($payment));
        $this->assertSame(0, AccountingJournalEntry::query()->count());
    }

    // ── US2 : idempotence ────────────────────────────────────────────────────

    public function test_reposting_is_idempotent_and_refreshes_amounts(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        $service = app(JournalPostingService::class);

        $service->postDocument($invoice);
        $service->postDocument($invoice->refresh());

        $this->assertSame(3, AccountingJournalEntry::query()->where('source_type', 'document')->where('source_id', $invoice->id)->count());
        $totals = $service->totalsForPeriod('2026-08');
        $this->assertSame(1190.0, $totals['debit']);
        $this->assertSame(1190.0, $totals['credit']);

        // Le montant est rafraîchi si le document change (re-posting).
        $invoice->update(['subtotal_ht' => 800.0, 'tax_amount' => 152.0, 'total_ttc' => 952.0]);
        $service->postDocument($invoice->refresh());

        $totals = $service->totalsForPeriod('2026-08');
        $this->assertSame(952.0, $totals['debit']);
        $this->assertSame(952.0, $totals['credit']);
    }

    public function test_unbalanced_posting_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        // HT/TVA incohérents avec le TTC → le service refuse d'écrire.
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        $invoice->update(['total_ttc' => 1300.0]);

        $this->expectException(UnbalancedJournalEntryException::class);
        app(JournalPostingService::class)->postDocument($invoice->refresh());
    }

    // ── US3 : journal + export ───────────────────────────────────────────────

    public function test_journal_lists_entries_by_period_with_totals(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->forgetCompany();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/accounting/journal?period=2026-08');
        $response->assertOk();
        $response->assertJsonPath('balanced', true);
        $response->assertJsonPath('closed', false);
        $response->assertJsonPath('totals.debit', 1190);
        $response->assertJsonPath('totals.credit', 1190);
        $response->assertJsonCount(3, 'entries');
        $response->assertJsonPath('entries.0.account_code', '411');
        $response->assertJsonPath('entries.0.piece', 'FAC-2026-0001');
    }

    public function test_journal_export_csv_is_expert_ready(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        // Numéro piégeux (injection de formule CSV) : doit être neutralisé.
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, '=CMD-2026-01');
        app(JournalPostingService::class)->postDocument($invoice);
        // Référence piégeuse (injection de formule CSV).
        $this->payment($company, $invoice, 1190.0, 'bank_transfer', '2026-08-10', 'recorded', '=2+2');
        app(JournalPostingService::class)->postPayment($invoice->payments()->firstOrFail());
        $this->forgetCompany();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->get('/api/v1/accounting/journal/export.csv?period=2026-08');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('date;piece;libelle;compte;intitule;debit;credit', $csv);
        // Injection de formule neutralisée sur la pièce (numéro contrôlé par l'utilisateur).
        $this->assertStringContainsString("'=CMD-2026-01", $csv);
        $this->assertStringContainsString('PAY-', $csv);
        // La référence de paiement (milieu de chaîne, sans préfixe de formule) passe telle quelle.
        $this->assertStringContainsString('Encaissement bank_transfer (=2+2)', $csv);
        // Ligne de totaux équilibrée.
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString('2380.00;2380.00', $csv);
    }

    public function test_journal_api_requires_manager_role(): void
    {
        $company = $this->company();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/journal?period=2026-08')->assertForbidden();
    }

    // ── US4 : clôture de période ─────────────────────────────────────────────

    public function test_closed_period_blocks_posting(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $service = app(JournalPostingService::class);
        $service->closePeriod('2026-07', '42');

        $this->assertTrue($service->isPeriodClosed('2026-07'));
        $this->assertFalse($service->isPeriodClosed('2026-08'));

        $julyInvoice = $this->document($company, 'invoice', 'sent', '2026-07-15', 500.0, 95.0);

        try {
            $service->postDocument($julyInvoice);
            $this->fail('Le posting dans une période clôturée doit lever PeriodClosedException.');
        } catch (PeriodClosedException $exception) {
            $this->assertStringContainsString('2026-07', $exception->getMessage());
        }

        // Rien n'a été écrit.
        $this->assertSame(0, AccountingJournalEntry::query()->count());
    }

    public function test_close_period_is_idempotent(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $service = app(JournalPostingService::class);

        $service->closePeriod('2026-06', '42');
        $service->closePeriod('2026-06', '42');

        $this->assertSame(1, AccountingClosedPeriod::query()->where('period', '2026-06')->count());
    }

    public function test_api_close_period_then_posting_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        $this->forgetCompany();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        // On passe d'abord la facture (période ouverte), puis on clôture.
        $this->bindCompany($company);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->forgetCompany();

        $close = $this->postJson('/api/v1/accounting/journal/periods/2026-08/close');
        $close->assertCreated();
        $close->assertJsonPath('period', '2026-08');

        // Le re-posting via l'API est refusé pour une période clôturée (422).
        $repost = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/journal');
        $this->assertSame(422, $repost->status());
    }

    // ── API : re-posting document ────────────────────────────────────────────

    public function test_api_post_document_journal_posts_document_and_payments(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        $this->payment($company, $invoice, 1190.0, 'bank_transfer', '2026-08-10');
        $this->forgetCompany();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/journal');
        $response->assertOk();
        // 3 écritures document + 2 écritures paiement.
        $response->assertJsonPath('entries', 5);
    }

    // ── Isolation tenant ─────────────────────────────────────────────────────

    public function test_journal_entries_are_tenant_scoped(): void
    {
        $companyA = $this->company();
        $this->bindCompany($companyA);
        $invoiceA = $this->document($companyA, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoiceA);
        $this->forgetCompany();

        $companyB = $this->company('MA');
        $this->bindCompany($companyB);

        // B ne voit aucune écriture de A.
        $this->assertSame(0, AccountingJournalEntry::query()->count());
        $this->assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
        $totals = app(JournalPostingService::class)->totalsForPeriod('2026-08');
        $this->assertSame(0.0, $totals['debit']);
        $this->assertSame(0.0, $totals['credit']);

        $this->forgetCompany();
    }
}
