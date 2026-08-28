<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use App\Modules\CRM\Domain\Models\CrmTask;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5711 — API CRM client V0 : routes, Policies, validation stricte.
 *
 * Couvre les statuts 401/403/404/422 par rôle et par tenant, le refus des
 * champs inconnus (commandes mutantes), les filtres/tris/pagination
 * allowlistés, la timeline append-only (pas de mutation) et l'isolation
 * tenant (404 cross-tenant via le binding scopé BelongsToCompany).
 */
class CrmApiHttpTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->manager = $manager;
    }

    // ---------------------------------------------------------------------
    // Auth & rôles
    // ---------------------------------------------------------------------

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/crm/leads')->assertStatus(401);
        $this->postJson('/api/v1/crm/leads', ['first_name' => 'A', 'last_name' => 'B'])->assertStatus(401);
    }

    public function test_plain_employee_cannot_create_lead(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ])->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // Validation stricte
    // ---------------------------------------------------------------------

    public function test_unknown_fields_are_rejected_on_store(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'evil_field' => 'pwned',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.unknown_fields.0', 'Champs non autorisés : evil_field');
    }

    public function test_unknown_status_is_rejected(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'status' => 'spam',
        ])->assertStatus(422);
    }

    public function test_unknown_sort_by_is_rejected(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/crm/leads?sort_by=password_hash')->assertStatus(422);
        $this->getJson('/api/v1/crm/leads?unknown_filter=1')->assertStatus(422);
    }

    public function test_stage_cannot_be_won_and_lost(): void
    {
        Sanctum::actingAs($this->manager);

        $pipeline = $this->createPipeline();

        $this->postJson("/api/v1/crm/pipelines/{$pipeline->id}/stages", [
            'name' => 'Les deux',
            'position' => 0,
            'is_won' => true,
            'is_lost' => true,
        ])->assertStatus(422);
    }

    public function test_opportunity_stage_must_belong_to_pipeline(): void
    {
        Sanctum::actingAs($this->manager);

        $pipelineA = $this->createPipeline('A');
        $pipelineB = $this->createPipeline('B');
        $stageB = $this->createStage($pipelineB, 0);

        $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Affaire',
            'pipeline_id' => $pipelineA->id,
            'stage_id' => $stageB->id,
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // CRUD happy path (manager)
    // ---------------------------------------------------------------------

    public function test_lead_crud_flow(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.test',
            'priority' => 'high',
        ])->assertCreated()->assertJsonPath('data.first_name', 'Jean');

        $leadId = $created->json('data.id');

        $this->getJson('/api/v1/crm/leads')->assertOk();
        $this->getJson("/api/v1/crm/leads/{$leadId}")->assertOk()->assertJsonPath('data.last_name', 'Dupont');

        $this->patchJson("/api/v1/crm/leads/{$leadId}", [
            'status' => 'qualified',
        ])->assertOk()->assertJsonPath('data.status', 'qualified');

        $this->deleteJson("/api/v1/crm/leads/{$leadId}")->assertStatus(204);
        $this->getJson("/api/v1/crm/leads/{$leadId}")->assertStatus(404);
    }

    public function test_pipeline_stages_and_opportunity_flow(): void
    {
        Sanctum::actingAs($this->manager);

        $pipeline = $this->createPipeline('Ventes');
        $stage = $this->createStage($pipeline, 0);

        $opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Contrat ACME',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'amount' => 12500.50,
        ])->assertCreated()->assertJsonPath('data.name', 'Contrat ACME');

        $opportunityId = $opportunity->json('data.id');

        $this->getJson('/api/v1/crm/opportunities?pipeline_id='.$pipeline->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        // Transition vers un stage gagnant → won_at horodaté.
        $wonStage = $this->createStage($pipeline, 1, 'Gagné', true, false);
        $this->patchJson("/api/v1/crm/opportunities/{$opportunityId}", [
            'stage_id' => $wonStage->id,
        ])->assertOk();

        $this->assertNotNull($this->getJson("/api/v1/crm/opportunities/{$opportunityId}")->json('data.won_at'));
    }

    public function test_activities_are_append_only(): void
    {
        Sanctum::actingAs($this->manager);

        $activity = $this->postJson('/api/v1/crm/activities', [
            'type' => 'call',
            'subject' => 'Appel de qualification',
        ])->assertCreated();

        $this->getJson('/api/v1/crm/activities?type=call')->assertOk()->assertJsonCount(1, 'data.data');

        // Timeline append-only : aucune route de mutation exposée.
        $this->putJson('/api/v1/crm/activities/'.$activity->json('data.id'), ['subject' => 'x'])->assertStatus(404);
        $this->deleteJson('/api/v1/crm/activities/'.$activity->json('data.id'))->assertStatus(404);
    }

    public function test_assignee_can_update_own_task_status(): void
    {
        Sanctum::actingAs($this->manager);

        $task = $this->postJson('/api/v1/crm/tasks', [
            'title' => 'Relancer le client',
            'assigned_to' => $this->manager->id,
        ])->assertCreated();

        $taskId = $task->json('data.id');

        $this->patchJson("/api/v1/crm/tasks/{$taskId}", [
            'status' => 'done',
        ])->assertOk()->assertJsonPath('data.status', 'done');
    }

    // ---------------------------------------------------------------------
    // Isolation tenant
    // ---------------------------------------------------------------------

    public function test_cross_tenant_binding_returns_404(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $leadA = CrmLead::query()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);

        Sanctum::actingAs($managerB);

        $this->getJson('/api/v1/crm/leads/'.$leadA->id)->assertStatus(404);
        $this->patchJson('/api/v1/crm/leads/'.$leadA->id, ['status' => 'qualified'])->assertStatus(404);
        $this->deleteJson('/api/v1/crm/leads/'.$leadA->id)->assertStatus(404);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function createPipeline(string $name = 'Pipeline'): CrmPipeline
    {
        return CrmPipeline::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
        ]);
    }

    private function createStage(CrmPipeline $pipeline, int $position, string $name = 'Stage', bool $isWon = false, bool $isLost = false): CrmPipelineStage
    {
        return CrmPipelineStage::query()->create([
            'company_id' => $this->company->id,
            'pipeline_id' => $pipeline->id,
            'name' => $name,
            'position' => $position,
            'is_won' => $isWon,
            'is_lost' => $isLost,
        ]);
    }
}
