<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5288 — activation guidée du module Comptabilité (wizard).
 *
 * Couvre : état initial incomplet, activation de bout en bout (settings +
 * contact de démonstration + facture EXEMPLE jetable), idempotence (pas de
 * doublon au rejeu), validation du payload settings, RBAC
 * (comptable/principal autorisés ; employé ordinaire et marketing refusés),
 * isolation tenant et cohérence de la facture d'exemple (totaux, statut,
 * marqueur `metadata.is_example`).
 */
class AccountingActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SAMPLE_CONTACT_EMAIL = 'demo@example.invalid';

    private const SAMPLE_INVOICE_NOTES = 'DOCUMENT EXEMPLE';

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
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
    private function activationPayload(): array
    {
        return [
            'currency' => 'DZD',
            'document_language' => 'fr',
            'template_style' => 'modern',
            'payment_terms' => '30 jours',
            'legal_mentions' => 'Mentions légales de test',
            'tva_rates' => [['label' => 'TVA standard', 'rate' => 19]],
            'number_series' => [
                'invoice' => 'FAC',
                'proforma' => 'PRO',
                'quote' => 'DEV',
                'credit_note' => 'AVOIR',
                'delivery_note' => 'BL',
                'receipt' => 'REC',
            ],
        ];
    }

    public function test_show_returns_incomplete_activation_on_empty_company(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/activation');

        $response->assertStatus(200);
        $response->assertJsonPath('data.completed', false);
        $response->assertJsonPath('data.contact', null);
        $response->assertJsonPath('data.example_invoice', null);

        // Les étapes contacts/facture sont à faux ; les settings peuvent être
        // déjà provisionnés par le listener CompanyCreated (#5232).
        $response->assertJsonPath('data.steps.contact', false);
        $response->assertJsonPath('data.steps.example_invoice', false);
    }

    public function test_complete_activates_module_end_to_end(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/activation', $this->activationPayload());

        $response->assertStatus(200);
        $response->assertJsonPath('data.completed', true);
        $response->assertJsonPath('data.steps.settings', true);
        $response->assertJsonPath('data.steps.contact', true);
        $response->assertJsonPath('data.steps.example_invoice', true);

        // Contact de démonstration créé (email marqueur, metadata is_sample).
        $contact = AccountingContact::query()
            ->where('company_id', $this->companyA->id)
            ->where('email', self::SAMPLE_CONTACT_EMAIL)
            ->first();
        $this->assertNotNull($contact);
        $this->assertSame('customer', $contact->type);
        $this->assertTrue($contact->metadata['is_sample'] ?? false);
        $response->assertJsonPath('data.contact.name', $contact->name);
        $response->assertJsonPath('data.contact.id', $contact->id);

        // Facture d'exemple créée : numérotation, statut brouillon, marqueur.
        $invoice = AccountingDocument::query()
            ->where('company_id', $this->companyA->id)
            ->where('notes', self::SAMPLE_INVOICE_NOTES)
            ->first();
        $this->assertNotNull($invoice);
        $this->assertSame(DocumentStatus::Draft->value, $invoice->status);
        $this->assertTrue($invoice->metadata['is_example'] ?? false);
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{4}$/', $invoice->number);
        $response->assertJsonPath('data.example_invoice.number', $invoice->number);
    }

    public function test_complete_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $first = $this->postJson('/api/v1/accounting/activation', $this->activationPayload());
        $first->assertStatus(200);

        $second = $this->postJson('/api/v1/accounting/activation', $this->activationPayload());
        $second->assertStatus(200);

        // Mêmes ressources, aucun doublon (idempotence par marqueurs).
        $this->assertSame(
            $first->json('data.contact.id'),
            $second->json('data.contact.id'),
        );
        $this->assertSame(
            $first->json('data.example_invoice.id'),
            $second->json('data.example_invoice.id'),
        );

        $this->assertSame(1, AccountingContact::query()
            ->where('company_id', $this->companyA->id)
            ->where('email', self::SAMPLE_CONTACT_EMAIL)
            ->count());
        $this->assertSame(1, AccountingDocument::query()
            ->where('company_id', $this->companyA->id)
            ->where('notes', self::SAMPLE_INVOICE_NOTES)
            ->count());
        $this->assertSame(2, AccountingDocumentLine::query()
            ->where('company_id', $this->companyA->id)
            ->count());
    }

    public function test_show_returns_completed_activation_after_complete(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/accounting/activation', $this->activationPayload())
            ->assertStatus(200);

        $response = $this->getJson('/api/v1/accounting/activation');

        $response->assertStatus(200);
        $response->assertJsonPath('data.completed', true);
        $this->assertNotNull($response->json('data.contact'));
        $this->assertNotNull($response->json('data.example_invoice'));
    }

    public function test_complete_rejects_unknown_currency(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $payload = $this->activationPayload();
        $payload['currency'] = 'XXX';

        $this->postJson('/api/v1/accounting/activation', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    public function test_rbac_forbids_employee_and_marketing(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));
        $this->getJson('/api/v1/accounting/activation')->assertStatus(403);
        $this->postJson('/api/v1/accounting/activation', $this->activationPayload())->assertStatus(403);

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));
        $this->getJson('/api/v1/accounting/activation')->assertStatus(403);
        $this->postJson('/api/v1/accounting/activation', $this->activationPayload())->assertStatus(403);
    }

    public function test_principal_can_activate(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $this->postJson('/api/v1/accounting/activation', $this->activationPayload())
            ->assertStatus(200)
            ->assertJsonPath('data.completed', true);
    }

    public function test_tenant_isolation(): void
    {
        // L'entreprise A active son module…
        Sanctum::actingAs($this->manager($this->companyA));
        $this->postJson('/api/v1/accounting/activation', $this->activationPayload())
            ->assertStatus(200)
            ->assertJsonPath('data.completed', true);

        // …l'entreprise B ne voit rien de A (aucune fuite cross-tenant).
        Sanctum::actingAs($this->manager($this->companyB));
        $response = $this->getJson('/api/v1/accounting/activation');

        $response->assertStatus(200);
        $response->assertJsonPath('data.completed', false);
        $response->assertJsonPath('data.contact', null);
        $response->assertJsonPath('data.example_invoice', null);

        $this->assertSame(0, AccountingContact::query()
            ->where('company_id', $this->companyB->id)
            ->where('email', self::SAMPLE_CONTACT_EMAIL)
            ->count());
        $this->assertSame(0, AccountingDocument::query()
            ->where('company_id', $this->companyB->id)
            ->where('notes', self::SAMPLE_INVOICE_NOTES)
            ->count());
    }

    public function test_example_invoice_is_draft_marked_and_totals_consistent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/accounting/activation', $this->activationPayload())
            ->assertStatus(200);

        $invoice = AccountingDocument::query()
            ->where('company_id', $this->companyA->id)
            ->where('notes', self::SAMPLE_INVOICE_NOTES)
            ->firstOrFail();

        $this->assertSame(DocumentStatus::Draft->value, $invoice->status);
        $this->assertTrue($invoice->metadata['is_example'] ?? false);
        $this->assertSame(19.0, (float) $invoice->tva_rate);
        // Lignes démo : 1000 + 500 → HT 1500, TVA 19 % → 285, TTC 1785.
        $this->assertSame(1500.0, (float) $invoice->subtotal_ht);
        $this->assertSame(285.0, (float) $invoice->tax_amount);
        $this->assertSame(1785.0, (float) $invoice->total_ttc);

        $lines = $invoice->lines()->get();
        $this->assertCount(2, $lines);
        $this->assertSame(1500.0, (float) $lines->sum('unit_price'));
    }
}
