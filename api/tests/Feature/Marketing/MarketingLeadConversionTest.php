<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5231 — conversion lead marketing qualifié → AccountingContact.
 *
 * Couvre : qualification par le rôle marketing/principal, création du
 * contact pré-rempli (source=marketing_lead, traçable), exclusion du
 * claim concurrent (409), RBAC (employé/comptable refusés), lecture
 * marketing read-only et isolation tenant (404 cross-tenant).
 */
class MarketingLeadConversionTest extends TestCase
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

    private function manager(Company $company, string $managerRole): Employee
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
    private function createLead(array $overrides = []): MarketingLead
    {
        /** @var MarketingLead $lead */
        $lead = MarketingLead::query()->create(array_merge([
            'external_id' => 'ext-'.uniqid('', false),
            'type' => MarketingLead::TYPE_DEMO_REQUEST,
            'email' => 'prospect@example.com',
            'locale' => 'fr',
            'country' => 'DZ',
            'status' => MarketingLead::STATUS_NEW,
            'payload' => ['name' => 'Prospect SARL'],
        ], $overrides));

        return $lead;
    }

    public function test_marketing_qualifies_lead_and_creates_contact(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $response = $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify');

        $response->assertStatus(201);
        $response->assertJsonPath('data.lead.status', 'converted');
        $response->assertJsonPath('data.lead.converted_company_id', $this->companyA->id);
        $response->assertJsonPath('data.contact.source', 'marketing_lead');
        $response->assertJsonPath('data.contact.marketing_lead_id', $lead->id);
        $response->assertJsonPath('data.contact.name', 'Prospect SARL');

        $this->assertDatabaseHas('accounting_contacts', [
            'company_id' => $this->companyA->id,
            'marketing_lead_id' => $lead->id,
            'source' => 'marketing_lead',
            'type' => 'customer',
            'email' => 'prospect@example.com',
        ]);

        /** @var MarketingLead $fresh */
        $fresh = MarketingLead::query()->findOrFail($lead->id);
        $this->assertSame(MarketingLead::STATUS_CONVERTED, $fresh->status);
        $this->assertSame($this->companyA->id, $fresh->converted_company_id);
    }

    public function test_contact_name_falls_back_to_email_local_part(): void
    {
        $lead = $this->createLead(['payload' => null]);

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $response = $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify');

        $response->assertStatus(201);
        $response->assertJsonPath('data.contact.name', 'prospect');
    }

    public function test_lead_claim_is_exclusive_between_tenants(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));
        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(201);

        // Le tenant B ne peut pas réclamer un lead déjà converti.
        Sanctum::actingAs($this->manager($this->companyB, 'principal'));
        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(409);

        $this->assertDatabaseMissing('accounting_contacts', [
            'company_id' => $this->companyB->id,
            'marketing_lead_id' => $lead->id,
        ]);
    }

    public function test_already_qualified_lead_cannot_be_reclaimed(): void
    {
        $lead = $this->createLead();
        $lead->status = MarketingLead::STATUS_QUALIFIED;
        $lead->save();

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(409);
    }

    public function test_ordinary_employee_is_forbidden(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(403);
    }

    public function test_comptable_role_is_forbidden_on_qualify(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(403);
    }

    public function test_marketing_reads_contact_of_own_lead(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));
        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(201);

        $response = $this->getJson('/api/v1/marketing/leads/'.$lead->id.'/contact');
        $response->assertStatus(200);
        $response->assertJsonPath('data.source', 'marketing_lead');
        $response->assertJsonPath('data.marketing_lead_id', $lead->id);
    }

    public function test_contact_of_lead_claimed_by_other_company_is_not_visible(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));
        $this->postJson('/api/v1/marketing/leads/'.$lead->id.'/qualify')->assertStatus(201);

        Sanctum::actingAs($this->manager($this->companyB, 'principal'));
        $this->getJson('/api/v1/marketing/leads/'.$lead->id.'/contact')->assertStatus(404);
    }

    public function test_unconverted_lead_has_no_contact(): void
    {
        $lead = $this->createLead();

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->getJson('/api/v1/marketing/leads/'.$lead->id.'/contact')->assertStatus(404);
    }
}
