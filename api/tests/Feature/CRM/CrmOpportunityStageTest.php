<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #9790 — faire avancer une opportunité dans le pipeline.
 *
 * Avant cette route, `GET /crm/opportunities` alimentait le kanban et aucun
 * chemin d'écriture n'existait : une affaire convertie restait en
 * `prospecting` indéfiniment et le CA gagné ne pouvait jamais être constaté.
 *
 * Les cas ci-dessous portent chacun une décision qui casserait le pipeline
 * commercial si elle était retirée — pas la simple couverture du code.
 */
class CrmOpportunityStageTest extends TestCase
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

    private function opportunity(Company $company, string $stage = 'prospecting', string $status = 'open'): CrmOpportunity
    {
        /** @var CrmOpportunity $opportunity */
        $opportunity = CrmOpportunity::query()->create([
            'company_id' => $company->id,
            'name' => 'Affaire de test',
            'stage' => $stage,
            'status' => $status,
            'amount' => '1000.00',
            'currency' => 'DZD',
        ]);

        return $opportunity;
    }

    // ─── Le chemin nominal ────────────────────────────────────────────────────

    public function test_moving_to_a_valid_stage_returns_200_and_updates_the_row(): void
    {
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'qualification',
        ])->assertOk()->assertJsonPath('data.stage', 'qualification');

        $opportunity->refresh();
        $this->assertSame('qualification', $opportunity->stage);
        $this->assertSame('open', $opportunity->status);
    }

    public function test_won_derives_status_closed(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'won',
        ])->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertSame('won', $opportunity->refresh()->stage);
        $this->assertSame('closed', $opportunity->status);
    }

    // ─── La whitelist ────────────────────────────────────────────────────────

    /**
     * Sans cette règle, `crm_opportunities.stage` (un `string(80)`) accepterait
     * n'importe quelle valeur et le kanban afficherait une colonne fantôme
     * qu'aucun filtre ne retrouverait.
     */
    public function test_unknown_stage_is_rejected_422(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'presque_gagne',
        ])->assertStatus(422)->assertJsonValidationErrors('stage');

        $this->assertSame('prospecting', $opportunity->refresh()->stage);
    }

    public function test_missing_stage_is_rejected_422(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stage');
    }

    // ─── Les états terminaux ─────────────────────────────────────────────────

    /**
     * Rouvrir une affaire close réécrirait l'historique commercial : le CA
     * constaté changerait après coup.
     */
    public function test_won_is_terminal(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA, 'won', 'closed');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'negotiation',
        ])->assertStatus(422)->assertJsonValidationErrors('stage');

        $this->assertSame('won', $opportunity->refresh()->stage);
    }

    public function test_lost_is_terminal(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA, 'lost', 'closed');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'prospecting',
        ])->assertStatus(422);

        $this->assertSame('lost', $opportunity->refresh()->stage);
    }

    // ─── Le motif de perte ───────────────────────────────────────────────────

    public function test_lost_requires_a_reason(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'lost',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason');
    }

    public function test_lost_with_reason_is_accepted(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'lost',
            'lost_reason' => 'Budget gelé côté client',
        ])->assertOk();

        $this->assertSame('lost', $opportunity->refresh()->stage);
    }

    /**
     * Un motif sur une affaire gagnée est une donnée trompeuse : il est refusé
     * plutôt que silencieusement ignoré.
     */
    public function test_reason_on_a_won_deal_is_refused(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'won',
            'lost_reason' => 'n\'a pas de sens',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason');
    }

    // ─── La trace ────────────────────────────────────────────────────────────

    public function test_the_previous_stage_is_journaled_in_notes_and_timeline(): void
    {
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'proposal',
        ])->assertOk();

        $this->assertStringContainsString('prospecting → proposal', (string) $opportunity->refresh()->notes);

        $activity = CrmActivity::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('type', CrmActivity::TYPE_STAGE_CHANGED)
            ->first();

        $this->assertNotNull($activity, 'La timeline du compte doit porter le changement d\'étape.');
        $this->assertSame($manager->company_id, $activity->company_id);
    }

    // ─── Isolation tenant et RBAC ────────────────────────────────────────────

    public function test_a_manager_cannot_move_a_deal_of_another_tenant(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $foreign = $this->opportunity($this->companyB);

        $this->patchJson("/api/v1/crm/opportunities/{$foreign->id}/stage", [
            'stage' => 'qualification',
        ])->assertNotFound();

        $this->assertSame('prospecting', $foreign->refresh()->stage);
    }

    public function test_an_ordinary_employee_cannot_move_a_deal(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'won',
        ])->assertForbidden();

        $this->assertSame('prospecting', $opportunity->refresh()->stage);
    }

    public function test_a_marketing_manager_can_move_a_deal(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'negotiation',
        ])->assertOk();
    }

    public function test_a_rh_manager_cannot_move_a_deal(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'rh'));

        $opportunity = $this->opportunity($this->companyA);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'negotiation',
        ])->assertForbidden();
    }
}
