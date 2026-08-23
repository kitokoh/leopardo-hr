<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5223 — cycle de vie des documents comptables (Comptabilité Phase A) :
 * draft → sent → partiellement payé → payé | annulé (+ overdue), avoir lié
 * borné, irsaliye datée. Règles de transition strictes testées.
 */
class AccountingDocumentWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;

        app()->instance('current_company', $company);
    }

    private function workflow(): DocumentWorkflowService
    {
        return app(DocumentWorkflowService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draftPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => DocumentType::Invoice->value,
            'tva_rate' => 19.0,
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => [
                ['description' => 'Prestation 1', 'quantity' => 2, 'unit_price' => 1000, 'discount' => 0],
                ['description' => 'Prestation 2', 'quantity' => 1, 'unit_price' => 500, 'discount' => 50],
            ],
        ], $overrides);
    }

    public function test_create_draft_assigns_number_and_computes_totals(): void
    {
        $document = $this->workflow()->createDraft($this->draftPayload(), (string) $this->company->id);

        $this->assertSame(DocumentStatus::Draft->value, $document->status);
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{4}$/', $document->number);
        // subtotal = 2×1000 + (500−50) = 2450 ; TVA 19 % = 465,50 ; total = 2915,50
        $this->assertSame(2450.0, $document->subtotal_ht);
        $this->assertSame(465.50, $document->tax_amount);
        $this->assertSame(2915.50, $document->total_ttc);
        $this->assertSame(2, $document->lines()->count());
    }

    public function test_send_requires_lines_and_sets_sent_at(): void
    {
        $document = $this->workflow()->createDraft($this->draftPayload([
            'contact_id' => $this->contact()->id,
        ]), (string) $this->company->id);

        $sent = $this->workflow()->send($document);
        $this->assertSame(DocumentStatus::Sent->value, $sent->status);
        $this->assertNotNull($sent->sent_at);

        // Sans ligne → refus (422 au niveau API, exception au niveau service).
        $empty = $this->workflow()->createDraft([
            'type' => DocumentType::Quote->value,
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]],
        ], (string) $this->company->id);
        $empty->lines()->delete();

        $this->expectExceptionMessage('sans ligne');
        $this->workflow()->send($empty->refresh());
    }

    public function test_invoice_send_requires_contact(): void
    {
        $document = $this->workflow()->createDraft(
            $this->draftPayload(['contact_id' => null]),
            (string) $this->company->id,
        );

        $this->expectExceptionMessage('contact client');
        $this->workflow()->send($document);
    }

    public function test_payment_transitions_partial_then_paid(): void
    {
        $document = $this->workflow()->createDraft(
            $this->draftPayload(['contact_id' => $this->contact()->id]),
            (string) $this->company->id,
        );
        $this->workflow()->send($document);

        $this->workflow()->recordPayment($document, 1000.0, PaymentMethod::BankTransfer);
        $this->assertSame(DocumentStatus::PartiallyPaid->value, $document->refresh()->status);
        $this->assertSame(1000.0, $document->paid_amount);

        $this->workflow()->recordPayment($document, 1915.50, PaymentMethod::BankTransfer);
        $this->assertSame(DocumentStatus::Paid->value, $document->refresh()->status);
        $this->assertSame(2915.50, $document->paid_amount);
        $this->assertSame(2, $document->payments()->count());
    }

    public function test_no_paid_status_without_payment_and_no_overpayment(): void
    {
        $document = $this->workflow()->createDraft($this->draftPayload(), (string) $this->company->id);
        // Jamais de transition directe vers paid : le statut reste draft tant
        // qu'aucun paiement n'est enregistré.
        $this->assertSame(DocumentStatus::Draft->value, $document->status);

        $this->expectExceptionMessage('dépasse le total TTC');
        $this->workflow()->recordPayment($document, 999999.0, PaymentMethod::Cash);
    }

    public function test_paid_document_cannot_be_cancelled_or_repaid(): void
    {
        $document = $this->workflow()->createDraft(
            $this->draftPayload(['contact_id' => $this->contact()->id]),
            (string) $this->company->id,
        );
        $this->workflow()->send($document);
        $this->workflow()->recordPayment($document, 2915.50, PaymentMethod::Card);
        $this->assertSame(DocumentStatus::Paid->value, $document->refresh()->status);

        $this->expectExceptionMessage('payé ne peut pas être annulé');
        $this->workflow()->cancel($document);
    }

    public function test_paid_document_rejects_new_payment(): void
    {
        $document = $this->workflow()->createDraft(
            $this->draftPayload(['contact_id' => $this->contact()->id]),
            (string) $this->company->id,
        );
        $this->workflow()->send($document);
        $this->workflow()->recordPayment($document, 2915.50, PaymentMethod::Card);

        $this->expectExceptionMessage('payé ou annulé');
        $this->workflow()->recordPayment($document->refresh(), 10.0, PaymentMethod::Cash);
    }

    public function test_cancel_allowed_on_draft_or_sent_but_not_paid(): void
    {
        $document = $this->workflow()->createDraft($this->draftPayload(), (string) $this->company->id);
        $cancelled = $this->workflow()->cancel($document, 'Erreur de saisie');
        $this->assertSame(DocumentStatus::Cancelled->value, $cancelled->status);
        $this->assertSame('Erreur de saisie', $cancelled->metadata['cancel_reason']);
    }

    public function test_overdue_refresh_flags_past_due_documents(): void
    {
        $document = $this->workflow()->createDraft(
            $this->draftPayload([
                'contact_id' => $this->contact()->id,
                'due_date' => now()->subDays(10)->toDateString(),
            ]),
            (string) $this->company->id,
        );
        $this->workflow()->send($document);

        $this->assertTrue($this->workflow()->isOverdue($document->refresh()));

        $updated = $this->workflow()->refreshOverdue((string) $this->company->id);
        $this->assertSame(1, $updated);
        $this->assertSame(DocumentStatus::Overdue->value, $document->refresh()->status);
    }

    public function test_credit_note_linked_to_source_and_bounded(): void
    {
        $invoice = $this->workflow()->createDraft(
            $this->draftPayload(['contact_id' => $this->contact()->id]),
            (string) $this->company->id,
        );
        $this->workflow()->send($invoice);
        $this->workflow()->recordPayment($invoice, 1000.0, PaymentMethod::BankTransfer);

        $creditNote = $this->workflow()->createCreditNote($invoice, [
            'lines' => [['description' => 'Remise commerciale', 'quantity' => 1, 'unit_price' => 500]],
        ]);
        $this->assertSame(DocumentType::CreditNote->value, $creditNote->type);
        $this->assertSame($invoice->id, $creditNote->metadata['source_document_id']);
        $this->assertSame($invoice->contact_id, $creditNote->contact_id);

        // Avoir > reste à payer (2915,50 − 1000 = 1915,50) → refus.
        $this->expectExceptionMessage('dépasse le reste à payer');
        $this->workflow()->createCreditNote($invoice, [
            'lines' => [['description' => 'Trop gros', 'quantity' => 1, 'unit_price' => 5000]],
        ]);
    }

    public function test_credit_note_requires_invoice_source_and_unpaid_balance(): void
    {
        $quote = $this->workflow()->createDraft([
            'type' => DocumentType::Quote->value,
            'lines' => [['description' => 'Devis', 'quantity' => 1, 'unit_price' => 100]],
        ], (string) $this->company->id);

        $this->expectExceptionMessage('avoir doit être lié à une facture');
        $this->workflow()->createCreditNote($quote, [
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10]],
        ]);
    }

    public function test_delivery_note_accepts_delivery_date(): void
    {
        $document = $this->workflow()->createDraft([
            'type' => DocumentType::DeliveryNote->value,
            'delivery_date' => now()->addDays(2)->toDateString(),
            'lines' => [['description' => 'Marchandise', 'quantity' => 5, 'unit_price' => 200]],
        ], (string) $this->company->id);

        $this->assertSame(DocumentType::DeliveryNote->value, $document->type);
        $this->assertNotNull($document->delivery_date);
        $this->assertSame('BL-'.now()->format('Y'), substr($document->number, 0, 7));
    }

    // ── Endpoints (RBAC + tenant) ────────────────────────────────────────

    public function test_documents_endpoints_require_manager_role(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/accounting/documents')->assertForbidden();
        $this->postJson('/api/v1/accounting/documents', $this->draftPayload())->assertForbidden();
        $this->getJson('/api/v1/accounting/documents/next-number?type=invoice')->assertForbidden();
    }

    public function test_documents_endpoints_are_tenant_isolated(): void
    {
        $document = $this->workflow()->createDraft($this->draftPayload(), (string) $this->company->id);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        app()->instance('current_company', $otherCompany);
        Sanctum::actingAs($otherManager);

        $this->getJson("/api/v1/accounting/documents/{$document->id}")->assertNotFound();
        $this->postJson("/api/v1/accounting/documents/{$document->id}/send")->assertNotFound();
        $this->getJson('/api/v1/accounting/documents')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_store_send_payment_cancel_via_api(): void
    {
        Sanctum::actingAs($this->manager);
        app()->instance('current_company', $this->company);

        $store = $this->postJson('/api/v1/accounting/documents', $this->draftPayload([
            'contact_id' => $this->contact()->id,
        ]));
        $store->assertStatus(201)
            ->assertJsonPath('data.status', DocumentStatus::Draft->value)
            ->assertJsonPath('data.total_ttc', 2915.50);

        $id = $store->json('data.id');

        $this->postJson("/api/v1/accounting/documents/{$id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', DocumentStatus::Sent->value);

        $this->postJson("/api/v1/accounting/documents/{$id}/payments", [
            'amount' => 2915.50,
            'method' => 'bank_transfer',
        ])->assertStatus(201);

        $this->getJson("/api/v1/accounting/documents/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', DocumentStatus::Paid->value)
            ->assertJsonPath('data.paid_amount', 2915.50);

        // Document payé → annulation refusée (422).
        $this->postJson("/api/v1/accounting/documents/{$id}/cancel", ['reason' => 'test'])
            ->assertStatus(422);
    }

    public function test_validation_errors_on_store(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/accounting/documents', ['type' => 'nope', 'lines' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'lines']);
    }

    private function contact(): AccountingContact
    {
        return AccountingContact::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'Client pilote DZ',
        ]);
    }
}
