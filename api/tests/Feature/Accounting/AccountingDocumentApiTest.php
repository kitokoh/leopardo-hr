<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API des documents comptables (#5223) — cycle de vie complet via le contrôleur :
 * création (brouillon numéroté), liste, détail, aperçu numéro, envoi,
 * encaissement, annulation, avoir — + RBAC et isolation tenant.
 *
 * Couvre la surface exposée par `AccountingDocumentController` (gate coverage
 * module Accounting ≥ 70 %, DoD #5228).
 */
class AccountingDocumentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private AccountingContact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var AccountingContact $customer */
        $customer = AccountingContact::query()->create([
            'company_id' => $companyA->id,
            'type' => 'customer',
            'name' => 'Client Doc',
            'email' => 'doc@example.com',
            'source' => 'manual',
        ]);
        $this->customer = $customer;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(): array
    {
        return [
            'type' => 'invoice',
            'contact_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'DZD',
            'tva_rate' => 19,
            'lines' => [
                ['description' => 'Prestation', 'quantity' => 2, 'unit_price' => 500],
                ['description' => 'Forfait', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ];
    }

    /**
     * Extrait l'id du document depuis la réponse de création.
     */
    private function documentIdFromResponse(mixed $response): int
    {
        $this->assertInstanceOf(TestResponse::class, $response);

        $id = $response->json('data.id');
        $this->assertIsInt($id);

        return $id;
    }

    public function test_store_creates_draft_with_number_and_lines(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/documents', $this->invoicePayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.status', 'draft');

        $number = $response->json('data.number');
        $this->assertIsString($number);
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{4}$/', $number);
        $lines = $response->json('data.lines');
        $this->assertIsArray($lines);
        $this->assertCount(2, $lines);
    }

    public function test_store_requires_lines_and_valid_type(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/accounting/documents', ['type' => 'invoice'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines');

        $payload = $this->invoicePayload();
        $payload['type'] = 'bogus';

        $this->postJson('/api/v1/accounting/documents', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_index_lists_and_filters_documents(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/accounting/documents', $this->invoicePayload())->assertStatus(201);

        $response = $this->getJson('/api/v1/accounting/documents');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'invoice');

        $this->getJson('/api/v1/accounting/documents?type=quote')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_show_returns_document_detail(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $document = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($this->postJson('/api/v1/accounting/documents', $this->invoicePayload())));

        $this->getJson('/api/v1/accounting/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.lines.0.description', 'Prestation');
    }

    public function test_next_number_previews_series(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/documents/next-number?type=invoice')
            ->assertOk()
            ->assertJsonPath('data.type', 'invoice');
    }

    public function test_next_number_requires_type(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/documents/next-number')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_send_transitions_draft_to_sent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $document = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($this->postJson('/api/v1/accounting/documents', $this->invoicePayload())));

        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_record_payment_updates_document_status(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $document = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($this->postJson('/api/v1/accounting/documents', $this->invoicePayload())));

        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/send')->assertOk();

        // Paiement partiel → partially_paid.
        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/payments', [
            'amount' => 500,
            'method' => 'cash',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'recorded')
            ->assertJsonPath('data.document_status', 'partially_paid')
            ->assertJsonPath('data.document_paid_amount', 500);

        $document->refresh();
        $this->assertSame('partially_paid', $document->status);

        // Paiement excédentaire → 422.
        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/payments', [
            'amount' => 999999,
            'method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_cancel_marks_document_cancelled(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $document = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($this->postJson('/api/v1/accounting/documents', $this->invoicePayload())));

        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/cancel', ['reason' => 'Erreur de saisie'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_credit_note_creates_linked_avoir(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $document = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($this->postJson('/api/v1/accounting/documents', $this->invoicePayload())));
        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/send')->assertOk();

        $response = $this->postJson('/api/v1/accounting/documents/'.$document->id.'/credit-note', [
            'notes' => 'Avoir de test',
            'lines' => [
                ['description' => 'Remise', 'quantity' => 1, 'unit_price' => 250],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'credit_note');

        $creditNote = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($response));
        $this->assertSame($document->id, $creditNote->metadata['source_document_id'] ?? null);
    }

    public function test_rbac_forbids_ordinary_employee(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/accounting/documents')->assertStatus(403);
        $this->postJson('/api/v1/accounting/documents', $this->invoicePayload())->assertStatus(403);
    }

    public function test_tenant_isolation_returns_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $document = AccountingDocument::query()->findOrFail($this->documentIdFromResponse($this->postJson('/api/v1/accounting/documents', $this->invoicePayload())));

        // L'entreprise B ne peut pas voir/modifier le document de A.
        Sanctum::actingAs($this->manager($this->companyB));
        $this->getJson('/api/v1/accounting/documents/'.$document->id)->assertStatus(404);
        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/send')->assertStatus(404);
        $this->postJson('/api/v1/accounting/documents/'.$document->id.'/cancel')->assertStatus(404);

        // La liste de B ne contient rien de A.
        $this->getJson('/api/v1/accounting/documents')->assertOk()->assertJsonCount(0, 'data');
    }
}
