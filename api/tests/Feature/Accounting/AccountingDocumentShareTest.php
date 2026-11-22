<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #9765 — `POST /accounting/documents/{document}/share`.
 *
 * Le partage était implémenté à moitié : modèle, service, portail public et
 * audit des accès existaient, mais AUCUNE route ne créait le jeton. Le vendeur
 * ne pouvait donc jamais envoyer un devis ou une facture à son client.
 *
 * Ce test verrouille :
 *  1. la création renvoie 201 avec un jeton de 64 caractères et une expiration ;
 *  2. le lien public sert bien le document (bout en bout, sans auth) ;
 *  3. l'expiration est paramétrable et bornée (1..365 jours) ;
 *  4. l'isolation tenant est fail-closed (document d'un autre tenant → 404) ;
 *  5. un collaborateur sans grant reste refusé (403).
 */
class AccountingDocumentShareTest extends TestCase
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
            'name' => 'Client Partage',
            'email' => 'partage@example.com',
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

    private function createInvoice(): AccountingDocument
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/documents', [
            'type' => 'invoice',
            'contact_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'DZD',
            'tva_rate' => 19,
            'lines' => [
                ['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ]);

        $response->assertStatus(201);

        return AccountingDocument::query()->findOrFail((int) $response->json('data.id'));
    }

    public function test_share_creates_token_with_expiry(): void
    {
        $document = $this->createInvoice();

        $response = $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [
            'email' => 'client@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_id', $document->id)
            ->assertJsonPath('data.shared_with_email', 'client@example.com');

        $token = (string) $response->json('data.share_token');
        $this->assertSame(64, strlen($token));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertStringContainsString($token, (string) $response->json('data.portal_url'));

        $this->assertDatabaseHas('accounting_document_shares', [
            'document_id' => $document->id,
            'share_token' => $token,
        ]);
    }

    public function test_public_portal_serves_the_shared_document(): void
    {
        $document = $this->createInvoice();

        $token = (string) $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [])
            ->assertStatus(201)
            ->json('data.share_token');

        // Route PUBLIQUE : aucune authentification.
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/accounting/documents/shared/{$token}")
            ->assertOk()
            ->assertJsonPath('data.number', $document->number)
            ->assertJsonPath('data.type', 'invoice');
    }

    public function test_expiry_is_configurable_and_bounded(): void
    {
        $document = $this->createInvoice();

        $response = $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [
            'expires_in_days' => 3,
        ])->assertStatus(201);

        $expiresAt = \Illuminate\Support\Carbon::parse((string) $response->json('data.expires_at'));
        $this->assertTrue($expiresAt->between(now()->addDays(2), now()->addDays(4)));

        // Bornes : 0 et 366 refusés.
        $this->postJson("/api/v1/accounting/documents/{$document->id}/share", ['expires_in_days' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expires_in_days');

        $this->postJson("/api/v1/accounting/documents/{$document->id}/share", ['expires_in_days' => 366])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expires_in_days');
    }

    public function test_share_is_tenant_isolated(): void
    {
        $document = $this->createInvoice();

        // Le manager de B ne peut pas partager le document de A.
        Sanctum::actingAs($this->manager($this->companyB));

        $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [])
            ->assertStatus(404);
    }

    public function test_employee_without_grant_cannot_share(): void
    {
        $document = $this->createInvoice();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [])
            ->assertStatus(403);
    }

    public function test_expired_share_is_not_served(): void
    {
        $document = $this->createInvoice();

        $token = (string) $this->postJson("/api/v1/accounting/documents/{$document->id}/share", [])
            ->assertStatus(201)
            ->json('data.share_token');

        // On force l'expiration dans le passé.
        AccountingDocumentShare::query()
            ->where('share_token', $token)
            ->update(['expires_at' => now()->subDay()]);

        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/accounting/documents/shared/{$token}")
            ->assertStatus(404);
    }
}
