<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Exceptions\CreditNoteRequiresSourceInvoiceException;
use App\Modules\Accounting\Domain\Exceptions\DeliveryNoteRequiresDeliveryDateException;
use App\Modules\Accounting\Domain\Exceptions\DocumentNotFullyPaidException;
use App\Modules\Accounting\Domain\Exceptions\InvalidDocumentTransitionException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\DocumentWorkflowService;
use App\Modules\Accounting\Infrastructure\Services\SequentialDocumentNumbering;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5223 — Workflow documents + numérotation paramétrable.
 *
 * Cycle de vie complet testé ; numérotation sans doublon (upsert atomique
 * ON CONFLICT, simulation de course par pré-insertion — convention repo) ;
 * règles de transition (pas de « payé » sans paiement, avoir lié, irsaliye
 * avec date de livraison, overdue calculé).
 */
class DocumentWorkflowNumberingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'UTC']);
        $this->company = $company;

        app()->instance('current_company', $company);
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

    private function addPayment(AccountingDocument $document, float $amount): void
    {
        AccountingPayment::create([
            'company_id' => $this->company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => 'bank_transfer',
            'received_at' => '2026-08-10 10:00:00',
            'status' => 'received',
        ]);
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

    // ── Workflow ──────────────────────────────────────────────────────────

    public function test_workflow_draft_to_sent(): void
    {
        $document = $this->makeDocument();
        $workflow = new DocumentWorkflowService;

        $workflow->transition($document, DocumentStatus::Sent);

        $this->assertSame(DocumentStatus::Sent->value, $document->refresh()->status);
    }

    public function test_workflow_invalid_transition_throws(): void
    {
        $document = $this->makeDocument();
        $workflow = new DocumentWorkflowService;

        $this->expectException(InvalidDocumentTransitionException::class);
        $workflow->transition($document, DocumentStatus::Paid);
    }

    public function test_paid_requires_full_payment(): void
    {
        $workflow = new DocumentWorkflowService;
        $document = $this->makeDocument();
        $workflow->transition($document, DocumentStatus::Sent);

        // Paiement partiel → refus.
        $this->addPayment($document, 1000);
        $this->expectException(DocumentNotFullyPaidException::class);
        $workflow->transition($document->refresh(), DocumentStatus::Paid);
    }

    public function test_paid_accepted_when_payments_cover_total(): void
    {
        $workflow = new DocumentWorkflowService;
        $document = $this->makeDocument();
        $workflow->transition($document, DocumentStatus::Sent);

        $this->addPayment($document, 2261);

        $workflow->transition($document->refresh(), DocumentStatus::Paid);

        $this->assertSame(DocumentStatus::Paid->value, $document->refresh()->status);
    }

    public function test_partially_paid_requires_partial_payment(): void
    {
        $workflow = new DocumentWorkflowService;
        $document = $this->makeDocument();
        $workflow->transition($document, DocumentStatus::Sent);

        // Aucun paiement → refus.
        $this->expectException(DocumentNotFullyPaidException::class);
        $workflow->transition($document, DocumentStatus::PartiallyPaid);
    }

    public function test_partially_paid_accepted_with_partial_payment(): void
    {
        $workflow = new DocumentWorkflowService;
        $document = $this->makeDocument();
        $workflow->transition($document, DocumentStatus::Sent);

        $this->addPayment($document, 1000);

        $workflow->transition($document->refresh(), DocumentStatus::PartiallyPaid);

        $this->assertSame(DocumentStatus::PartiallyPaid->value, $document->refresh()->status);
    }

    public function test_credit_note_requires_source_invoice_before_issue(): void
    {
        $workflow = new DocumentWorkflowService;
        $creditNote = $this->makeDocument(DocumentType::CreditNote);

        $this->expectException(CreditNoteRequiresSourceInvoiceException::class);
        $workflow->transition($creditNote, DocumentStatus::Sent);
    }

    public function test_credit_note_linked_to_invoice_can_be_issued(): void
    {
        $workflow = new DocumentWorkflowService;
        $invoice = $this->makeDocument(DocumentType::Invoice);
        $creditNote = $this->makeDocument(DocumentType::CreditNote);

        $workflow->linkCreditNote($creditNote, $invoice);
        $workflow->transition($creditNote, DocumentStatus::Sent);

        $this->assertSame($invoice->id, $creditNote->refresh()->source_document_id);
        $this->assertSame(DocumentStatus::Sent->value, $creditNote->refresh()->status);
    }

    public function test_link_credit_note_guards_types_and_company(): void
    {
        $workflow = new DocumentWorkflowService;
        $invoice = $this->makeDocument(DocumentType::Invoice);
        $quote = $this->makeDocument(DocumentType::Quote);

        $this->expectException(\InvalidArgumentException::class);
        $workflow->linkCreditNote($quote, $invoice);
    }

    public function test_delivery_note_requires_delivery_date_before_issue(): void
    {
        $workflow = new DocumentWorkflowService;
        $deliveryNote = $this->makeDocument(DocumentType::DeliveryNote);

        $this->expectException(DeliveryNoteRequiresDeliveryDateException::class);
        $workflow->transition($deliveryNote, DocumentStatus::Sent);
    }

    public function test_delivery_note_with_delivery_date_can_be_issued(): void
    {
        $workflow = new DocumentWorkflowService;
        $deliveryNote = $this->makeDocument(DocumentType::DeliveryNote, ['delivery_date' => '2026-08-02']);

        $workflow->transition($deliveryNote, DocumentStatus::Sent);

        $this->assertSame(DocumentStatus::Sent->value, $deliveryNote->refresh()->status);
    }

    public function test_refresh_overdue_marks_past_due_documents(): void
    {
        $workflow = new DocumentWorkflowService;

        $overdue = $this->makeDocument(overrides: ['due_date' => '2026-07-31']);
        $workflow->transition($overdue, DocumentStatus::Sent);

        $future = $this->makeDocument(overrides: ['due_date' => '2026-09-30']);
        $workflow->transition($future, DocumentStatus::Sent);

        $count = $workflow->refreshOverdue($this->company, Carbon::parse('2026-08-15'));

        $this->assertSame(1, $count);
        $this->assertSame(DocumentStatus::Overdue->value, $overdue->refresh()->status);
        $this->assertSame(DocumentStatus::Sent->value, $future->refresh()->status);
    }
}
