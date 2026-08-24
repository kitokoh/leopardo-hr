<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5222 — CRUD contacts client/fournisseur + RBAC comptable.
 *
 * Couvre : création/liste/détail/mise à jour/suppression, filtres,
 * isolation tenant via l'API (404 cross-tenant), RBAC (comptable/principal
 * autorisés ; employé ordinaire et marketing refusés), validation et
 * chiffrement du NIF au repos.
 */
class AccountingContactCrudTest extends TestCase
{
    use RefreshTenantDatabase;

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
     * @param  array<string, mixed>  $overrides
     */
    private function createContact(Company $company, array $overrides = []): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create(array_merge([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Société Alpha',
            'email' => 'alpha@example.com',
            'currency' => 'DZD',
        ], $overrides));

        return $contact;
    }

    public function test_comptable_can_create_contact(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'supplier',
            'name' => 'Fournisseur Beta',
            'legal_name' => 'Beta SARL',
            'tax_id' => '000016000000000',
            'email' => 'beta@example.com',
            'currency' => 'DZD',
            'metadata' => ['sector' => 'logistics'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Fournisseur Beta');
        $response->assertJsonPath('data.type', 'supplier');
        $response->assertJsonPath('data.source', 'manual');
        $response->assertJsonPath('data.tax_id', '000016000000000');

        $this->assertDatabaseHas('accounting_contacts', [
            'company_id' => $this->companyA->id,
            'name' => 'Fournisseur Beta',
        ]);
    }

    public function test_tax_id_is_encrypted_at_rest(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
            'name' => 'Client Secret',
            'tax_id' => 'NIF-TOP-SECRET-123',
        ]);

        $response->assertStatus(201);

        $stored = DB::table('accounting_contacts')
            ->where('company_id', $this->companyA->id)
            ->value('tax_id');

        $this->assertNotSame('NIF-TOP-SECRET-123', $stored, 'Le NIF doit être chiffré au repos.');

        /** @var AccountingContact $fresh */
        $fresh = AccountingContact::query()->where('name', 'Client Secret')->firstOrFail();
        $this->assertSame('NIF-TOP-SECRET-123', $fresh->tax_id, 'Le cast encrypted doit restituer la valeur.');
    }

    public function test_comptable_can_list_contacts_with_filters(): void
    {
        $this->createContact($this->companyA, ['type' => 'customer', 'name' => 'Client Un']);
        $this->createContact($this->companyA, ['type' => 'supplier', 'name' => 'Fournisseur Deux']);

        Sanctum::actingAs($this->manager($this->companyA));

        $all = $this->getJson('/api/v1/accounting/contacts');
        $all->assertStatus(200);
        $all->assertJsonCount(2, 'data');
        $all->assertJsonPath('meta.total', 2);

        $customers = $this->getJson('/api/v1/accounting/contacts?type=customer');
        $customers->assertStatus(200);
        $customers->assertJsonCount(1, 'data');
        $customers->assertJsonPath('data.0.name', 'Client Un');

        $search = $this->getJson('/api/v1/accounting/contacts?search=Fournisseur');
        $search->assertStatus(200);
        $search->assertJsonCount(1, 'data');
    }

    public function test_contact_is_tenant_scoped_via_api(): void
    {
        $contact = $this->createContact($this->companyA);

        Sanctum::actingAs($this->manager($this->companyB));

        $list = $this->getJson('/api/v1/accounting/contacts');
        $list->assertStatus(200);
        $list->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/accounting/contacts/'.$contact->id)->assertStatus(404);
        $this->putJson('/api/v1/accounting/contacts/'.$contact->id, ['name' => 'Hack'])->assertStatus(404);
        $this->deleteJson('/api/v1/accounting/contacts/'.$contact->id)->assertStatus(404);
    }

    public function test_comptable_can_show_update_and_destroy_contact(): void
    {
        $contact = $this->createContact($this->companyA, ['name' => 'Avant']);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/contacts/'.$contact->id)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Avant');

        $this->putJson('/api/v1/accounting/contacts/'.$contact->id, [
            'name' => 'Après',
            'payment_terms' => '30 J',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Après')
            ->assertJsonPath('data.payment_terms', '30 J');

        $this->assertDatabaseHas('accounting_contacts', [
            'id' => $contact->id,
            'name' => 'Après',
        ]);

        $this->deleteJson('/api/v1/accounting/contacts/'.$contact->id)->assertStatus(204);
        $this->assertDatabaseMissing('accounting_contacts', ['id' => $contact->id]);
    }

    public function test_validation_rejects_invalid_type(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'alien',
            'name' => 'X',
        ])->assertStatus(422);

        $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
        ])->assertStatus(422);
    }

    public function test_ordinary_employee_is_forbidden(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/accounting/contacts')->assertStatus(403);
        $this->postJson('/api/v1/accounting/contacts', ['type' => 'customer', 'name' => 'X'])
            ->assertStatus(403);
    }

    public function test_marketing_role_is_forbidden(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->getJson('/api/v1/accounting/contacts')->assertStatus(403);
    }

    public function test_principal_has_read_access(): void
    {
        $this->createContact($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $this->getJson('/api/v1/accounting/contacts')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/accounting/contacts')->assertStatus(401);
    }
}
