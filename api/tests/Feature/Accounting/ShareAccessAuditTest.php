<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\SendDocumentEmail;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5522 — Audit des accès au portail client (réponse à incident RGPD).
 *
 * Endpoint GET /api/v1/accounting/documents/shared/{document}/accesses :
 * qui a consulté / téléchargé un document partagé, quand, depuis quelle IP.
 * RBAC principal/comptable, isolation tenant fail-closed (404), pagination.
 */
class ShareAccessAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'active']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'status' => 'active']);
        $this->companyB = $companyB;

        Storage::fake('private');
        Mail::fake();
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
        ]);

        return $manager;
    }

    /** Crée un document partagé via le code RÉEL (PDF + partage + email), retourne (document, token). */
    /** @return array{0: AccountingDocument, 1: string} */
    private function sharedDocument(Company $company, string $suffix): array
    {
        app()->instance('current_company', $company);

        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client '.$suffix,
            'email' => 'client-'.$suffix.'@exemple.dz',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-5522-'.$suffix,
            'status' => 'draft',
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
        ]);

        $token = app(SendDocumentEmail::class)->handle($document, 'client-'.$suffix.'@exemple.dz');

        return [$document, $token];
    }

    public function test_principal_lists_share_accesses_for_document(): void
    {
        [$document, $token] = $this->sharedDocument($this->companyA, 'A');

        // Deux accès publics réels (code de prod) : une consultation + un téléchargement.
        $this->getJson('/api/v1/accounting/documents/shared/'.$token)->assertOk();
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')->assertOk();

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/documents/shared/'.$document->id.'/accesses');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'accounting.share.download')
            ->assertJsonPath('data.1.action', 'accounting.share.info')
            ->assertJsonPath('data.0.module', 'accounting')
            ->assertJsonPath('data.0.ip_address', '127.0.0.1')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'action', 'module', 'request_id', 'ip_address', 'user_agent', 'created_at',
                ]],
                'links',
                'meta' => ['current_page', 'last_page', 'total'],
            ]);
    }

    public function test_comptable_lists_share_accesses_for_document(): void
    {
        [$document, $token] = $this->sharedDocument($this->companyA, 'A');
        $this->getJson('/api/v1/accounting/documents/shared/'.$token)->assertOk();

        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->getJson('/api/v1/accounting/documents/shared/'.$document->id.'/accesses')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_employee_is_forbidden(): void
    {
        [$document] = $this->sharedDocument($this->companyA, 'A');

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/documents/shared/'.$document->id.'/accesses')->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/accounting/documents/shared/1/accesses')->assertUnauthorized();
    }

    public function test_unknown_document_returns_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/documents/shared/999999999/accesses')->assertNotFound();
    }

    public function test_cross_tenant_document_returns_404(): void
    {
        // Document partagé du tenant B — le manager du tenant A ne doit rien voir.
        [$document] = $this->sharedDocument($this->companyB, 'B');

        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/documents/shared/'.$document->id.'/accesses')->assertNotFound();
    }

    public function test_accesses_are_paginated(): void
    {
        [$document, $token] = $this->sharedDocument($this->companyA, 'A');

        // 4 consultations + 1 téléchargement = 5 accès tracés.
        foreach (range(1, 4) as $i) {
            $this->getJson('/api/v1/accounting/documents/shared/'.$token)->assertOk();
        }
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')->assertOk();

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/documents/shared/'.$document->id.'/accesses?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3);
    }
}
