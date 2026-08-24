<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API des documents comptables (#5223) — cycle de vie complet via les
 * endpoints : création d'un brouillon numéroté, liste/filtres, détail,
 * aperçu du prochain numéro, envoi (draft→sent), encaissement, annulation,
 * avoir lié. RBAC comptable/principal + isolation tenant (404) + validation.
 */
class AccountingDocumentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
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

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function contact(Company $company): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client DZ',
            'email' => 'client@exemple.dz',
        ]);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draftPayload(AccountingContact $contact, array $overrides = []): array
    {
        return array_merge([
            'type' => 'invoice',
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-10',
            'due_date' => '2026-08-25',
            'currency' => 'DZD',
            'tva_rate' => 19,
            'notes' => 'Prestation août 2026',
            'lines' => [
                ['description' => 'Conseil', 'quantity' => 2, 'unit_price' => 500, 'discount' => 0],
            ],
        ], $overrides);
    }

    public function test_comptable_creates_lists_and_reads_documents(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $contact = $this->contact($company);

        Sanctum::actingAs($this->manager($company));

        // Création d'un brouillon numéroté (D charge HT + TVA).
        $created = $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact))
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonStructure(['data' => ['id', 'number', 'lines']]);

        $id = $created->json('data.id');
        $this->assertSame(1000.0, (float) $created->json('data.subtotal_ht'));
        $this->assertSame(190.0, (float) $created->json('data.tax_amount'));
        $this->assertSame(1190.0, (float) $created->json('data.total_ttc'));

        // Liste paginée avec filtres.
        $this->getJson('/api/v1/accounting/documents?type=invoice&status=draft&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/accounting/documents?type=quote')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Détail (lignes + contact).
        $this->getJson('/api/v1/accounting/documents/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_next_number_previews_configured_series(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        Sanctum::actingAs($this->manager($company));

        $this->getJson('/api/v1/accounting/documents/next-number?type=invoice')
            ->assertOk()
            ->assertJsonStructure(['data' => ['type', 'number']])
            ->assertJsonPath('data.type', 'invoice');
    }

    public function test_document_lifecycle_send_payment_cancel(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $contact = $this->contact($company);

        Sanctum::actingAs($this->manager($company));

        $id = $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact))
            ->assertStatus(201)
            ->json('data.id');

        // draft → sent.
        $this->postJson('/api/v1/accounting/documents/'.$id.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        // Paiement partiel (bank_transfer) — data = paiement + soldes doc.
        $payment = $this->postJson('/api/v1/accounting/documents/'.$id.'/payments', [
            'amount' => 500,
            'method' => 'bank_transfer',
            'reference' => 'VIR-001',
            'received_at' => '2026-08-12',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'recorded')
            ->assertJsonPath('data.document_status', 'partially_paid');

        $this->assertSame(500.0, (float) $payment->json('data.document_paid_amount'));

        // Annulation motivée d'un document envoyé non soldé.
        $this->postJson('/api/v1/accounting/documents/'.$id.'/cancel', ['reason' => 'Erreur de facturation'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_credit_note_linked_to_sent_invoice(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $contact = $this->contact($company);

        Sanctum::actingAs($this->manager($company));

        $id = $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact))
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson('/api/v1/accounting/documents/'.$id.'/send')->assertOk();

        $avoir = $this->postJson('/api/v1/accounting/documents/'.$id.'/credit-note', [
            'notes' => 'Avoir partiel',
            'lines' => [
                ['description' => 'Remboursement', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.type', 'credit_note');

        // L'avoir est lié à la facture source (metadata.source_document_id).
        $this->assertSame($id, $avoir->json('data.metadata.source_document_id'));
    }

    public function test_credit_note_on_draft_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $contact = $this->contact($company);

        Sanctum::actingAs($this->manager($company));

        $id = $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact))
            ->assertStatus(201)
            ->json('data.id');

        // Une facture brouillon ne peut pas générer d'avoir (422).
        $this->postJson('/api/v1/accounting/documents/'.$id.'/credit-note', [
            'lines' => [['description' => 'Avoir', 'unit_price' => 10]],
        ])->assertStatus(422);
    }

    public function test_store_validation_rejects_bad_payload(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $contact = $this->contact($company);

        Sanctum::actingAs($this->manager($company));

        // Type de document inconnu.
        $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact, ['type' => 'magic']))
            ->assertStatus(422);

        // Lignes absentes.
        $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact, ['lines' => []]))
            ->assertStatus(422);
    }

    public function test_employee_forbidden_and_cross_tenant_is_404(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $contact = $this->contact($company);

        Sanctum::actingAs($this->manager($company));
        $id = $this->postJson('/api/v1/accounting/documents', $this->draftPayload($contact))
            ->assertStatus(201)
            ->json('data.id');

        // Employé ordinaire → 403 (RBAC comptable/principal).
        Sanctum::actingAs($this->employee($company));
        $this->getJson('/api/v1/accounting/documents/'.$id)->assertForbidden();

        // Manager d'un AUTRE tenant → 404 (isolation fail-closed).
        $otherCompany = $this->company();
        $this->bindCompany($otherCompany);
        Sanctum::actingAs($this->manager($otherCompany));

        $this->getJson('/api/v1/accounting/documents/'.$id)->assertNotFound();
        $this->postJson('/api/v1/accounting/documents/'.$id.'/send')->assertNotFound();
        $this->postJson('/api/v1/accounting/documents/'.$id.'/cancel')->assertNotFound();
    }
}
