<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\SequentialDocumentNumbering;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5223 — Workflow documents + numérotation paramétrable.
 *
 * Cycle de vie complet testé sur le service CANONIQUE Application
 * (`DocumentWorkflowService`) : draft → sent → partially_paid → paid | cancelled
 * (+ overdue calculé), jamais de `paid` sans paiement couvrant le total,
 * avoir lié à sa facture source ; numérotation sans doublon (upsert atomique
 * ON CONFLICT, simulation de course par pré-insertion — convention repo).
 *
 * #6266 — la version Infrastructure dupliquée a été supprimée ; ce test ne
 * couvre plus que le service Application (une classe métier = un endroit).
 */
class DocumentWorkflowNumberingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private AccountingContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'UTC']);
        $this->company = $company;

        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Workflow',
            'email' => 'workflow@exemple.dz',
        ]);
        $this->contact = $contact;

        app()->instance('current_company', $company);
    }

    private function workflow(): DocumentWorkflowService
    {
        return new DocumentWorkflowService(new SequentialDocumentNumbering);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDocument(DocumentType $type = DocumentType::Invoice, array $overrides = []): AccountingDocument
    {
        /** @var AccountingDocument $document */
        $document = AccountingDocument::create(array_merge([
            'company_id' => $this->company->id,
            'type' => $type->value,
            'number' => 'TMP-'.random_int(100000, 999999),
            'status' => DocumentStatus::Draft->value,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
            'paid_amount' => 0,
        ], $overrides));

        return $document;
    }

    private function addLine(AccountingDocument $document, string $description = 'Prestation workflow', float $unitPrice = 1900.0): void
    {
        AccountingDocumentLine::create([
            'company_id' => $this->company->id,
            'document_id' => $document->id,
            'description' => $description,
            'quantity' => 1.0,
            'unit_price' => $unitPrice,
            'discount' => 0.0,
            'sort_order' => (int) $document->lines()->count(),
        ]);
    }

    /**
     * Facture émise (sent) via le workflow canonique — contact + ligne requis.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sentInvoice(array $overrides = []): AccountingDocument
    {
        $document = $this->makeDocument(DocumentType::Invoice, array_merge(['contact_id' => $this->contact->id], $overrides));
        $this->addLine($document);

        $this->workflow()->send($document);

        return $document->refresh();
    }

    // ── Numérotation ──────────────────────────────────────────────────────

    public function test_numbering_sequence_is_consecutive(): void
    {
        $numbering = new SequentialDocumentNumbering;

        $year = now()->format('Y');

        $this->assertSame("FAC-{$year}-0001", $numbering->nextNumber($this->company->id, DocumentType::Invoice));
        $this->assertSame("FAC-{$year}-0002", $numbering->nextNumber($this->company->id, DocumentType::Invoice));
        $this->assertSame("FAC-{$year}-0003", $numbering->nextNumber($this->company->id, DocumentType::Invoice));
        $this->assertSame("PRF-{$year}-0001", $numbering->nextNumber($this->company->id, DocumentType::Proforma));
        $this->assertSame("AVR-{$year}-0001", $numbering->nextNumber($this->company->id, DocumentType::CreditNote));
    }

    public function test_numbering_uses_custom_series_from_settings(): void
    {
        AccountingSettings::create([
            'company_id' => $this->company->id,
            'number_series' => ['invoice' => 'FA', 'quote' => 'DE'],
        ]);

        $numbering = new SequentialDocumentNumbering;
        $year = now()->format('Y');

        $this->assertSame("FA-{$year}-0001", $numbering->nextNumber($this->company->id, DocumentType::Invoice));
        $this->assertSame("DE-{$year}-0001", $numbering->nextNumber($this->company->id, DocumentType::Quote));
    }

    public function test_numbering_upserts_existing_counter_without_duplicate(): void
    {
        // Simulation de course (convention repo #4978) : le compteur existe déjà
        // comme si un appel concurrent l'avait créé.
        DB::table('accounting_number_counters')->insert([
            'company_id' => $this->company->id,
            'type' => DocumentType::Invoice->value,
            'series' => 'FAC',
            'year' => (int) now()->format('Y'),
            'last_number' => 41,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $numbering = new SequentialDocumentNumbering;
        $year = now()->format('Y');

        // L'upsert ON CONFLICT incrémente — jamais de doublon.
        $this->assertSame("FAC-{$year}-0042", $numbering->nextNumber($this->company->id, DocumentType::Invoice));
        $this->assertSame("FAC-{$year}-0043", $numbering->nextNumber($this->company->id, DocumentType::Invoice));
    }

    public function test_numbering_is_unique_over_many_calls(): void
    {
        $numbering = new SequentialDocumentNumbering;

        $numbers = [];
        for ($i = 0; $i < 100; $i++) {
            $numbers[] = $numbering->nextNumber($this->company->id, DocumentType::Invoice);
        }

        $this->assertCount(100, array_unique($numbers));
    }

    // ── Workflow (service canonique Application) ──────────────────────────

    public function test_workflow_draft_to_sent(): void
    {
        $document = $this->makeDocument(DocumentType::Invoice, ['contact_id' => $this->contact->id]);
        $this->addLine($document);

        $this->workflow()->send($document);

        $this->assertSame(DocumentStatus::Sent->value, $document->refresh()->status);
        $this->assertNotNull($document->sent_at);
    }

    public function test_workflow_send_requires_lines(): void
    {
        $document = $this->makeDocument(DocumentType::Invoice, ['contact_id' => $this->contact->id]);

        $this->expectException(DocumentWorkflowException::class);
        $this->workflow()->send($document);
    }

    public function test_paid_requires_full_payment(): void
    {
        $document = $this->sentInvoice();

        // Paiement partiel → jamais paid, seulement partially_paid.
        $this->workflow()->recordPayment($document, 1000.0, PaymentMethod::Cash);
        $this->assertSame(DocumentStatus::PartiallyPaid->value, $document->refresh()->status);

        // Paiement au-delà du total → refus explicite (jamais de paid sans solde).
        $this->expectException(DocumentWorkflowException::class);
        $this->workflow()->recordPayment($document->refresh(), 99999.0, PaymentMethod::Cash);
    }

    public function test_paid_accepted_when_payments_cover_total(): void
    {
        $document = $this->sentInvoice();

        $this->workflow()->recordPayment($document, 2261.0, PaymentMethod::BankTransfer);

        $this->assertSame(DocumentStatus::Paid->value, $document->refresh()->status);
        $this->assertEqualsWithDelta(2261.0, (float) $document->paid_amount, 0.001);
    }

    public function test_partially_paid_with_partial_payment(): void
    {
        $document = $this->sentInvoice();

        // Sans paiement, le document reste sent.
        $this->assertSame(DocumentStatus::Sent->value, $document->status);

        $this->workflow()->recordPayment($document, 1000.0, PaymentMethod::Cash);

        $this->assertSame(DocumentStatus::PartiallyPaid->value, $document->refresh()->status);
    }

    public function test_credit_note_requires_invoice_source(): void
    {
        $quote = $this->makeDocument(DocumentType::Quote);

        $this->expectException(DocumentWorkflowException::class);
        $this->workflow()->createCreditNote($quote, [
            'lines' => [['description' => 'Avoir', 'quantity' => 1.0, 'unit_price' => 500.0]],
        ]);
    }

    public function test_credit_note_linked_to_invoice_can_be_issued(): void
    {
        $invoice = $this->sentInvoice();

        $creditNote = $this->workflow()->createCreditNote($invoice, [
            'lines' => [['description' => 'Avoir — réduction', 'quantity' => 1.0, 'unit_price' => 500.0]],
        ]);

        $this->assertSame(DocumentType::CreditNote->value, $creditNote->type);
        $this->assertSame((string) $invoice->id, (string) ($creditNote->metadata['source_document_id'] ?? ''));

        $this->workflow()->send($creditNote);

        $this->assertSame(DocumentStatus::Sent->value, $creditNote->refresh()->status);
    }

    public function test_cancel_rejects_paid_document(): void
    {
        $document = $this->sentInvoice();
        $this->workflow()->recordPayment($document, 2261.0, PaymentMethod::BankTransfer);

        $this->expectException(DocumentWorkflowException::class);
        $this->workflow()->cancel($document->refresh());
    }

    public function test_refresh_overdue_marks_past_due_documents(): void
    {
        $overdue = $this->sentInvoice(['due_date' => '2026-07-31']);
        $future = $this->sentInvoice(['due_date' => '2026-09-30']);

        $count = $this->workflow()->refreshOverdue((string) $this->company->id);

        $this->assertSame(1, $count);
        $this->assertSame(DocumentStatus::Overdue->value, $overdue->refresh()->status);
        $this->assertSame(DocumentStatus::Sent->value, $future->refresh()->status);
    }
}
