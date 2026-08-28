<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API CRM client — Issue #5711 (CRM-V0-07).
 *
 * Verrouille :
 *   1. l'authentification (401) et le RBAC (403 MANAGER_REQUIRED pour un
 *      employé, 403 INSUFFICIENT_ROLE pour un manager hors périmètre) ;
 *   2. l'isolation tenant : toute ressource d'un autre tenant est
 *      introuvable (404), toute référence étrangère est refusée (422) ;
 *   3. la validation stricte : champs inconnus refusés (422 `_unknown`),
 *      statuts/enums/tri/pagination allowlistés (422) ;
 *   4. les invariants métier : étape gagnée+perdue refusée, contact primaire
 *      unique, stage appartenant au pipeline, transition de tâche → done ;
 *   5. l'append-only de la timeline (pas de route de mise à jour → 405).
 */
class CrmApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = $companyB;
        /** @var Employee $principalA */
        $principalA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $this->principalA = $principalA;
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        $this->managerB = $managerB;
    }

    private function actingAsA(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    private function createLeadA(array $overrides = []): CrmLead
    {
        /** @var CrmLead $lead */
        $lead = CrmLead::query()->create(array_merge([
            'company_id' => $this->companyA->id,
            'first_name' => 'Amine',
            'last_name' => 'Benali',
            'email' => 'amine.benali@example.com',
            'status' => 'new',
            'priority' => 'medium',
        ], $overrides));

        return $lead;
    }

    private function createPipelineA(string $name = 'Ventes directes'): CrmPipeline
    {
        /** @var CrmPipeline $pipeline */
        $pipeline = CrmPipeline::query()->create([
            'company_id' => $this->companyA->id,
            'name' => $name,
        ]);

        return $pipeline;
    }

    private function createStageA(CrmPipeline $pipeline, string $name = 'Nouveau', int $position = 0): CrmPipelineStage
    {
        /** @var CrmPipelineStage $stage */
        $stage = CrmPipelineStage::query()->create([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipeline->id,
            'name' => $name,
            'position' => $position,
        ]);

        return $stage;
    }

    // ── Authentification & RBAC ───────────────────────────────────────────────

    public function test_unauthenticated_crm_route_returns_401(): void
    {
        $this->getJson('/api/v1/crm/leads')->assertStatus(401);
    }

    public function test_ordinary_employee_gets_403_manager_required(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $this->actingAsA($employee);

        $this->getJson('/api/v1/crm/leads')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_out_of_scope_manager_role_gets_403_insufficient_role(): void
    {
        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create(['company_id' => $this->companyA->id]);
        $this->actingAsA($deptManager);

        $this->getJson('/api/v1/crm/leads')
            ->assertStatus(403)
            ->assertJsonPath('error', 'INSUFFICIENT_ROLE');
    }

    // ── Leads ─────────────────────────────────────────────────────────────────

    public function test_principal_can_create_lead(): void
    {
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Amine',
            'last_name' => 'Benali',
            'email' => 'amine.benali@example.com',
            'status' => 'new',
            'priority' => 'high',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Amine')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.company_id', $this->companyA->id);
    }

    public function test_cross_tenant_lead_is_not_found(): void
    {
        $lead = $this->createLeadA();
        $this->actingAsA($this->managerB);

        $this->getJson("/api/v1/crm/leads/{$lead->id}")->assertStatus(404);
    }

    public function test_cross_tenant_lead_update_is_not_found(): void
    {
        $lead = $this->createLeadA();
        $this->actingAsA($this->managerB);

        $this->putJson("/api/v1/crm/leads/{$lead->id}", ['first_name' => 'X'])->assertStatus(404);
    }

    public function test_unknown_field_on_lead_is_rejected(): void
    {
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Amine',
            'last_name' => 'Benali',
            'firs_name' => 'typo volontaire',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('_unknown');
    }

    public function test_invalid_lead_status_is_rejected(): void
    {
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Amine',
            'last_name' => 'Benali',
            'status' => 'bogus-status',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_lead_sort_is_rejected(): void
    {
        $this->actingAsA($this->principalA);

        $this->getJson('/api/v1/crm/leads?sort_by=evil_column')->assertStatus(422);
    }

    public function test_out_of_bounds_per_page_is_rejected(): void
    {
        $this->actingAsA($this->principalA);

        $this->getJson('/api/v1/crm/leads?per_page=5000')->assertStatus(422);
    }

    public function test_cross_tenant_owner_is_rejected(): void
    {
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Amine',
            'last_name' => 'Benali',
            'owner_id' => $this->managerB->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('owner_id');
    }

    public function test_lead_list_is_paginated_and_filtered(): void
    {
        $this->createLeadA();
        $this->createLeadA(['first_name' => 'Sara', 'status' => 'contacted']);
        $this->actingAsA($this->principalA);

        $this->getJson('/api/v1/crm/leads?status=new&per_page=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.status', 'new');
    }

    // ── Opportunités ──────────────────────────────────────────────────────────

    public function test_principal_can_create_opportunity_with_pipeline_stage(): void
    {
        $pipeline = $this->createPipelineA();
        $stage = $this->createStageA($pipeline);
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Contrat TechCorp',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'amount' => 250000,
            'currency' => 'DZD',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Contrat TechCorp')
            ->assertJsonPath('data.is_won', false)
            ->assertJsonPath('data.is_lost', false);
    }

    public function test_opportunity_stage_must_belong_to_pipeline(): void
    {
        $pipelineA = $this->createPipelineA('Pipeline A');
        $pipelineB = $this->createPipelineA('Pipeline B');
        $stageOfB = $this->createStageA($pipelineB, 'Étape B', 0);
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Incohérent',
            'pipeline_id' => $pipelineA->id,
            'stage_id' => $stageOfB->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_cross_tenant_pipeline_reference_is_rejected(): void
    {
        /** @var CrmPipeline $pipelineB */
        $pipelineB = CrmPipeline::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Pipeline tenant B',
        ]);
        /** @var CrmPipelineStage $stageB */
        $stageB = CrmPipelineStage::query()->create([
            'company_id' => $this->companyB->id,
            'pipeline_id' => $pipelineB->id,
            'name' => 'Étape B',
            'position' => 0,
        ]);
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Cross tenant',
            'pipeline_id' => $pipelineB->id,
            'stage_id' => $stageB->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pipeline_id');
    }

    // ── Timeline (append-only) ────────────────────────────────────────────────

    public function test_activity_is_created_and_append_only(): void
    {
        $lead = $this->createLeadA();
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/activities', [
            'subject' => 'Premier appel',
            'activity_type' => 'call',
            'related_type' => 'lead',
            'related_id' => $lead->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.activity_type', 'call');

        // Append-only : pas de route de mise à jour (405).
        $this->putJson('/api/v1/crm/activities/1', ['subject' => 'X'])->assertStatus(405);
    }

    public function test_activity_related_type_is_allowlisted(): void
    {
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/activities', [
            'subject' => 'Événement',
            'activity_type' => 'note',
            'related_type' => 'pipeline',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('related_type');
    }

    // ── Tâches ────────────────────────────────────────────────────────────────

    public function test_task_completion_is_derived_from_status(): void
    {
        $this->actingAsA($this->principalA);

        $created = $this->postJson('/api/v1/crm/tasks', [
            'subject' => 'Relancer le prospect',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_at' => '2026-09-15',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.completed_at', null);

        $taskId = $created->json('data.id');

        $this->putJson("/api/v1/crm/tasks/{$taskId}", ['status' => 'done'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'done')
            ->assertJsonNotNull('data.completed_at');
    }

    // ── Comptes & contacts ────────────────────────────────────────────────────

    public function test_account_and_single_primary_contact(): void
    {
        $this->actingAsA($this->principalA);

        $account = $this->postJson('/api/v1/crm/accounts', [
            'name' => 'TechCorp Algérie',
            'country' => 'DZ',
            'industry' => 'Technologie',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'TechCorp Algérie');
        $accountId = $account->json('data.id');

        $this->postJson('/api/v1/crm/contacts', [
            'account_id' => $accountId,
            'first_name' => 'Lina',
            'last_name' => 'Mansouri',
            'is_primary' => true,
        ])->assertStatus(201);

        // Second primaire → 422 (contrôle applicatif, pas de 500).
        $this->postJson('/api/v1/crm/contacts', [
            'account_id' => $accountId,
            'first_name' => 'Riad',
            'last_name' => 'Zerrouki',
            'is_primary' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_primary');
    }

    public function test_cross_tenant_account_reference_is_rejected(): void
    {
        /** @var \App\Modules\CRM\Domain\Models\CrmAccount $accountB */
        $accountB = \App\Modules\CRM\Domain\Models\CrmAccount::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Compte tenant B',
        ]);
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/contacts', [
            'account_id' => $accountB->id,
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account_id');
    }

    // ── Pipelines ─────────────────────────────────────────────────────────────

    public function test_pipeline_stage_cannot_be_won_and_lost(): void
    {
        $pipeline = $this->createPipelineA();
        $this->actingAsA($this->principalA);

        $this->postJson("/api/v1/crm/pipelines/{$pipeline->id}/stages", [
            'name' => 'Gagné et perdu',
            'is_won' => true,
            'is_lost' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_lost');
    }

    public function test_pipeline_name_is_unique_per_company(): void
    {
        $this->createPipelineA('Ventes directes');
        $this->actingAsA($this->principalA);

        $this->postJson('/api/v1/crm/pipelines', ['name' => 'Ventes directes'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
