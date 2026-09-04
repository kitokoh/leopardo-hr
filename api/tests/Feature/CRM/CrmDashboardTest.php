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
 * Issue #5721 — Dashboard CRM : pipeline & qualité des données.
 *
 * Couvre : agrégats pipeline exacts (totaux, par stage, stagnation, owners
 * sans opportunité ouverte, tâches en retard), métriques de qualité
 * (accounts sans contact primaire, contacts incomplets, doublons estimés),
 * isolation tenant stricte (aucune fuite cross-tenant) et RBAC
 * (principal/rh/marketing autorisés ; comptable et employé refusés).
 */
class CrmDashboardTest extends TestCase
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
        // Tenant A : 3 stages (2 ouverts, 1 gagné), 3 opportunités, 1 tâche en retard.
        $pipelineA = $this->createCrmPipeline(['company_id' => $this->companyA->id, 'name' => 'Pipeline A']);
        $stageProspection = $this->createCrmStage(['company_id' => $this->companyA->id, 'pipeline_id' => $pipelineA, 'name' => 'Prospection', 'position' => 0]);
        $stageNegociation = $this->createCrmStage(['company_id' => $this->companyA->id, 'pipeline_id' => $pipelineA, 'name' => 'Négociation', 'position' => 1]);
        $stageGagne = $this->createCrmStage(['company_id' => $this->companyA->id, 'pipeline_id' => $pipelineA, 'name' => 'Gagné', 'position' => 2, 'is_won' => true]);

        $this->createCrmOpportunity(['company_id' => $this->companyA->id, 'stage_id' => $stageProspection, 'name' => 'Opp A1', 'amount' => 10000]);
        $this->createCrmOpportunity(['company_id' => $this->companyA->id, 'stage_id' => $stageNegociation, 'name' => 'Opp A2', 'amount' => 25000]);
        $this->createCrmOpportunity(['company_id' => $this->companyA->id, 'stage_id' => $stageGagne, 'name' => 'Opp A3', 'amount' => 5000]);

        $this->createCrmTask(['company_id' => $this->companyA->id, 'due_at' => now()->subDay(), 'status' => 'todo']);

        $accountA = $this->createCrmAccount(['company_id' => $this->companyA->id, 'name' => 'Alpha SARL']);
        $this->createCrmAccount(['company_id' => $this->companyA->id, 'name' => 'Beta SARL']);
        $this->createCrmContact(['company_id' => $this->companyA->id, 'account_id' => $accountA, 'email' => 'contact@alpha.example', 'phone' => null, 'is_primary' => true]);
        $this->createCrmContact(['company_id' => $this->companyA->id, 'account_id' => $accountA, 'email' => 'contact@alpha.example', 'phone' => '0600000000']);
        $this->createCrmContact(['company_id' => $this->companyA->id, 'account_id' => $accountA, 'email' => null, 'phone' => null]);

        // Tenant B : données qui ne doivent JAMAIS fuiter vers A.
        $pipelineB = $this->createCrmPipeline(['company_id' => $this->companyB->id, 'name' => 'Pipeline B']);
        $stageB = $this->createCrmStage(['company_id' => $this->companyB->id, 'pipeline_id' => $pipelineB, 'name' => 'Stage B', 'position' => 0]);
        $this->createCrmOpportunity(['company_id' => $this->companyB->id, 'stage_id' => $stageB, 'name' => 'Opp B1', 'amount' => 999999]);
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

    public function test_pipeline_aggregates_are_exact(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/dashboard/pipeline');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(2, $data['totals']['open_count']);
        $this->assertSame(35000.0, $data['totals']['open_value']);
        $this->assertSame(1, $data['totals']['won_count']);
        $this->assertSame(0, $data['totals']['lost_count']);

        // Par stage : Prospection 1 / 10 000, Négociation 1 / 25 000.
        /** @var array<int, array<string, mixed>> $byStageRaw */
        $byStageRaw = $data['by_stage'] ?? [];
        $byStage = collect($byStageRaw)->keyBy('stage_name');
        $this->assertSame(1, $byStage['Prospection']['count'] ?? null);
        $this->assertSame(10000.0, $byStage['Prospection']['value'] ?? null);
        $this->assertSame(1, $byStage['Négociation']['count'] ?? null);
        $this->assertSame(25000.0, $byStage['Négociation']['value'] ?? null);

        $this->assertSame(1, $data['overdue_tasks']);
    }

    public function test_pipeline_is_tenant_isolated(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/dashboard/pipeline');

        $response->assertOk();
        // L'opportunité B (999 999) ne doit pas apparaître.
        $this->assertSame(35000.0, $response->json('data.totals.open_value'));
    }

    public function test_quality_metrics(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/crm/dashboard/quality');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(2, $data['accounts_total']);
        // Beta SARL n'a aucun contact primaire.
        $this->assertSame(1, $data['accounts_without_primary_contact']);
        $this->assertSame(3, $data['contacts_total']);
        // 1 contact sans email + 1 sans téléphone.
        $this->assertSame(1, $data['contacts_without_email']);
        $this->assertSame(1, $data['contacts_without_phone']);
        // contact@alpha.example apparaît 2 fois (normalisé) → 1 groupe doublon.
        $this->assertSame(1, $data['duplicate_contacts_estimate']);
    }

    public function test_stagnant_opportunities_detection(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Une opportunité ouverte sans activité depuis 30+ jours.
        $accountId = $this->createCrmAccount(['company_id' => $this->companyA->id, 'name' => 'Gamma SARL']);
        $stageId = DB::table('crm_pipeline_stages')->where('company_id', $this->companyA->id)->where('name', 'Prospection')->value('id');
        $this->createCrmOpportunity(['company_id' => $this->companyA->id, 'account_id' => $accountId, 'stage_id' => $stageId, 'name' => 'Opp stagnante', 'amount' => 1000]);

        $response = $this->getJson('/api/v1/crm/dashboard/pipeline');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.stagnant_opportunities'));
    }

    public function test_owners_without_open_opportunities(): void
    {
        $owner = $this->manager($this->companyA, 'rh');
        Sanctum::actingAs($this->manager($this->companyA));

        $this->createCrmAccount(['company_id' => $this->companyA->id, 'name' => 'Delta SARL', 'owner_id' => $owner->id]);

        $response = $this->getJson('/api/v1/crm/dashboard/pipeline');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $ownersRaw */
        $ownersRaw = $response->json('data.owners_without_open_opportunities') ?? [];
        $owners = collect($ownersRaw)->pluck('owner_id');

        $this->assertContains($owner->id, $owners);
    }

    public function test_comptable_and_employee_are_forbidden(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));
        $this->getJson('/api/v1/crm/dashboard/pipeline')->assertStatus(403);
        $this->getJson('/api/v1/crm/dashboard/quality')->assertStatus(403);

        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));
        $this->getJson('/api/v1/crm/dashboard/pipeline')->assertStatus(403);
    }

    public function test_marketing_manager_can_access(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->getJson('/api/v1/crm/dashboard/pipeline')->assertOk();
        $this->getJson('/api/v1/crm/dashboard/quality')->assertOk();
    }
}
