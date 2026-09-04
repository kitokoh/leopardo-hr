<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\CreatesCrmSchema;
use Tests\TestCase;

/**
 * Issue #5719 — Recherche CRM tenant-scoped.
 *
 * Couvre : recherche accounts+contacts, filtres allowlistés (type/statut/
 * owner), isolation tenant stricte (aucune fuite cross-tenant), RBAC
 * (principal/rh/marketing autorisés ; comptable et employé ordinaire
 * refusés), validation (q min 2 caractères, enums fermés) et pagination.
 */
class CrmSearchTest extends TestCase
{
    use CreatesCrmSchema;
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCrmSchemaIfMissing();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        $this->seedData();
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function seedData(): void
    {
        $this->createCrmAccount([
            'company_id' => $this->companyA->id,
            'name' => 'Transports Alpha SARL',
            'legal_name' => 'Transports Alpha SARL',
            'status' => 'active',
        ]);
        $this->createCrmAccount([
            'company_id' => $this->companyA->id,
            'name' => 'Beta Logistique',
            'status' => 'inactive',
        ]);
        $this->createCrmAccount([
            'company_id' => $this->companyB->id,
            'name' => 'Transports Alpha Maroc',
            'status' => 'active',
        ]);

        $this->createCrmContact([
            'company_id' => $this->companyA->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'status' => 'active',
        ]);
        $this->createCrmContact([
            'company_id' => $this->companyA->id,
            'first_name' => 'Marie',
            'last_name' => 'Durand',
            'email' => 'marie.durand@example.com',
            'status' => 'active',
        ]);
        $this->createCrmContact([
            'company_id' => $this->companyB->id,
            'first_name' => 'Jean',
            'last_name' => 'Martin',
            'email' => 'jean.martin@example.com',
            'status' => 'active',
        ]);
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

    public function test_principal_can_search_accounts_and_contacts(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/search?q=alpha');

        $response->assertOk();
        $response->assertJsonPath('data.0.type', 'account');
        $response->assertJsonPath('data.0.name', 'Transports Alpha SARL');

        /** @var array<int, array<string, mixed>> $searchData */
        $searchData = $response->json('data') ?? [];
        $types = collect($searchData)->pluck('type')->unique()->values();

        $this->assertContains('account', $types);
        $this->assertContains('contact', $types);
    }

    public function test_search_filters_by_type_contact(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/search?q=jean&type=contact');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $searchData2 */
        $searchData2 = $response->json('data') ?? [];
        $types = collect($searchData2)->pluck('type')->unique();

        $this->assertEquals(['contact'], $types->values()->all());
    }

    public function test_cross_tenant_isolation_no_leak(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // « alpha » matche aussi « Transports Alpha Maroc » (tenant B) et
        // « jean » matche « Jean Martin » (tenant B) — aucun ne doit sortir.
        $response = $this->getJson('/api/v1/crm/search?q=alpha');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $searchData */
        $searchData = $response->json('data') ?? [];
        $names = collect($searchData)->pluck('name');

        $this->assertNotContains('Transports Alpha Maroc', $names);

        $response2 = $this->getJson('/api/v1/crm/search?q=jean&type=contact');

        $response2->assertOk();
        /** @var array<int, array<string, mixed>> $searchData2 */
        $searchData2 = $response2->json('data') ?? [];
        $lastNames = collect($searchData2)->pluck('last_name');

        $this->assertNotContains('Martin', $lastNames);
        $this->assertContains('Dupont', $lastNames);
    }

    public function test_tenant_b_does_not_see_tenant_a_data(): void
    {
        Sanctum::actingAs($this->manager($this->companyB));

        $response = $this->getJson('/api/v1/crm/search?q=transports');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $searchData */
        $searchData = $response->json('data') ?? [];
        $names = collect($searchData)->pluck('name');

        $this->assertContains('Transports Alpha Maroc', $names);
        $this->assertNotContains('Transports Alpha SARL', $names);
    }

    public function test_status_filter_is_allowlisted_and_applied(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/search?q=transports&type=account&status=inactive');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'Beta Logistique');

        $this->getJson('/api/v1/crm/search?q=transports&status=churned')
            ->assertStatus(422);
    }

    public function test_owner_filter_is_applied(): void
    {
        $owner = $this->manager($this->companyA, 'rh');
        Sanctum::actingAs($this->manager($this->companyA));

        DB::table('crm_accounts')->where('name', 'Beta Logistique')
            ->update(['owner_id' => $owner->id]);

        $response = $this->getJson('/api/v1/crm/search?q=logistique&type=account&owner_id='.$owner->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_query_too_short_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/crm/search?q=a')->assertStatus(422);
    }

    public function test_unknown_type_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/crm/search?q=alpha&type=lead')
            ->assertStatus(422);
    }

    public function test_ordinary_employee_is_forbidden(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/crm/search?q=alpha')->assertStatus(403);
    }

    public function test_comptable_role_is_forbidden(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->getJson('/api/v1/crm/search?q=alpha')->assertStatus(403);
    }

    public function test_marketing_manager_can_search(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->getJson('/api/v1/crm/search?q=alpha')->assertOk();
    }

    public function test_pagination_is_bounded(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/search?q=dupond&per_page=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('meta')['last_page'] ?? $response->json('meta.last_page'));

        $this->getJson('/api/v1/crm/search?q=dupond&per_page=500')->assertStatus(422);
    }
}
