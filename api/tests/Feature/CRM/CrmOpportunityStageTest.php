<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
<<<<<<< HEAD
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Enums\CrmAutomationTrigger;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmAutomation;
use App\Modules\CRM\Domain\Models\CrmAutomationRun;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
=======
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Laravel\Sanctum\Sanctum;
>>>>>>> origin/main
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
<<<<<<< HEAD
 * Pipeline commercial deplacable — #9780.
 *
 * ## Le defaut que ce test protege
 *
 * `GET /crm/opportunities` existait (kanban par `stage`) et AUCUNE route ne
 * changeait `stage`. Un commercial voyait son pipeline sans pouvoir le faire
 * avancer : une affaire convertie restait `prospecting` a vie, et rien ne
 * pouvait etre marque gagne ou perdu.
 *
 * ## Ce qui est verifie ici
 *
 * - un deplacement ouvre le parcours, et `status` SUIT `stage` ;
 * - `won` ferme l'affaire et devient terminal ;
 * - `lost` exige un motif, et le motif reste dans l'historique ;
 * - un retour en arriere dans le parcours ouvert est permis (un client peut
 *   revenir a l'etape precedente) — seul un etat TERMINAL est verrouille ;
 * - une etape hors whitelist est refusee en 422 ;
 * - une opportunite d'un autre tenant est introuvable (404).
=======
 * Issue #9790 — faire avancer une opportunité dans le pipeline.
 *
 * Avant cette route, `GET /crm/opportunities` alimentait le kanban et aucun
 * chemin d'écriture n'existait : une affaire convertie restait en
 * `prospecting` indéfiniment et le CA gagné ne pouvait jamais être constaté.
 *
 * Les cas ci-dessous portent chacun une décision qui casserait le pipeline
 * commercial si elle était retirée — pas la simple couverture du code.
>>>>>>> origin/main
 */
class CrmOpportunityStageTest extends TestCase
{
    use RefreshTenantDatabase;

<<<<<<< HEAD
    public function test_an_opportunity_can_advance_and_status_follows(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'prospecting');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'qualification'])
            ->assertStatus(200)
            ->assertJsonPath('data.stage', 'qualification')
            // `status` est DERIVE : une affaire ouverte le reste.
            ->assertJsonPath('data.status', 'open');
    }

    public function test_going_back_is_allowed_before_a_terminal_stage(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'negotiation');

        // Un client qui revient sur son budget ramene l'affaire en `proposal`.
        // Interdire ce retour forcerait le commercial a mentir sur l'etape.
        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'proposal'])
            ->assertStatus(200)
            ->assertJsonPath('data.stage', 'proposal');
    }

    public function test_winning_closes_the_deal_and_is_terminal(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'negotiation');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'won'])
            ->assertStatus(200)
            ->assertJsonPath('data.stage', 'won')
            ->assertJsonPath('data.status', 'closed');

        // Rouvrir reecrirait l'historique commercial (un CA deja compte
        // redeviendrait « en cours »). On refuse, et on le dit.
        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'proposal'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_losing_requires_a_reason_and_keeps_it_in_history(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'proposal');

        // Sans motif, le bilan trimestriel est inexplicable.
        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'lost'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lost_reason']);

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'lost',
            'lost_reason' => 'Budget gele jusqu au T3',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.stage', 'lost')
            ->assertJsonPath('data.status', 'closed');

        // Le motif et l'etape precedente survivent dans l'historique : c'est
        // la seule trace de la trajectoire, la table n'a pas de colonne dediee.
        $notes = (string) CrmOpportunity::query()->findOrFail($opportunity->id)->notes;
        self::assertStringContainsString('proposal -> lost', $notes);
        self::assertStringContainsString('Budget gele jusqu au T3', $notes);
    }

    public function test_lost_is_terminal_too(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'lost');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'negotiation'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_a_stage_outside_the_whitelist_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'prospecting');

        // La colonne n'est qu'un string(80) : sans cette regle, n'importe
        // quelle valeur pouvait y entrer et casser le regroupement du kanban.
        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'teleported'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_the_stage_change_is_recorded_in_the_timeline(): void
    {
        $company = $this->createCompany();
        $manager = $this->actingAsManager($company);

        $opportunity = $this->opportunity($company, 'qualification');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'proposal'])
            ->assertStatus(200);

        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opportunity->id,
            'type' => CrmActivity::TYPE_STAGE_CHANGED,
            'created_by' => $manager->id,
        ]);
    }

    public function test_another_tenants_opportunity_is_not_found(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsManager($companyA);

        $opportunity = $this->opportunity($companyA, 'prospecting');

        $companyB = $this->createCompany();
        $this->actingAsManager($companyB);

        // 404, jamais 403 : l'API ne revele pas l'existence de l'objet.
        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'won'])
            ->assertStatus(404);

        // Et l'affaire n'a pas bouge.
        self::assertSame('prospecting', (string) CrmOpportunity::query()->withoutGlobalScopes()->findOrFail($opportunity->id)->stage);
    }

    /**
     * L'evenement `crm.opportunity.stage_changed` est REELLEMENT emis.
     *
     * ## Ce que ce test protege
     *
     * L'enum declarait l'evenement, `AutomationEngine::dispatch()` savait le
     * consommer, et **personne ne le produisait** : une automatisation
     * « quand une affaire passe a gagne, creer une tache » ne se declenchait
     * jamais. Un test qui se contenterait de verifier que la transition
     * repond 200 ne verrait pas la difference — c'est precisement pourquoi le
     * trou a survecu.
     *
     * On branche donc une automatisation reelle sur l'evenement et on verifie
     * qu'une execution est persistee (`crm_automation_runs`).
     */
    public function test_the_stage_change_emits_the_automation_event(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $automation = CrmAutomation::query()->create([
            'name' => 'Tache a la victoire',
            'trigger_event' => CrmAutomationTrigger::OPPORTUNITY_STAGE_CHANGED,
            'conditions' => [],
            'actions' => [
                ['type' => CrmAutomationActionType::CREATE_TASK, 'config' => ['title' => 'Feter la signature']],
            ],
            'status' => 'active',
        ]);

        $opportunity = $this->opportunity($company, 'negotiation');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'won'])
            ->assertStatus(200);

        // L'automatisation a bien ete declenchee par l'evenement.
        $this->assertDatabaseHas('crm_automation_runs', [
            'automation_id' => $automation->id,
            'trigger_event' => CrmAutomationTrigger::OPPORTUNITY_STAGE_CHANGED,
            'entity_type' => 'opportunity',
            'entity_id' => (string) $opportunity->id,
        ]);
    }

    /**
     * Deux transitions SUCCESSIVES declenchent DEUX executions.
     *
     * Le moteur dedoublonne par `run_key` = (automation, evenement, entite).
     * Pour un changement d'etape, la meme affaire declenche l'evenement
     * plusieurs fois dans sa vie : sans discriminant, la deuxieme transition
     * serait classee « deja traitee » et l'automatisation ne tournerait qu'une
     * fois par affaire — silencieusement. `dedupe_key` porte l'etape cible.
     */
    public function test_two_successive_transitions_run_the_automation_twice(): void
    {
        $company = $this->createCompany();
        $this->actingAsManager($company);

        $automation = CrmAutomation::query()->create([
            'name' => 'Suivi a chaque etape',
            'trigger_event' => CrmAutomationTrigger::OPPORTUNITY_STAGE_CHANGED,
            'conditions' => [],
            'actions' => [
                ['type' => CrmAutomationActionType::CREATE_TASK, 'config' => ['title' => 'Relancer le client']],
            ],
            'status' => 'active',
        ]);

        $opportunity = $this->opportunity($company, 'prospecting');

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'qualification'])
            ->assertStatus(200);
        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", ['stage' => 'proposal'])
            ->assertStatus(200);

        self::assertSame(
            2,
            CrmAutomationRun::query()->where('automation_id', $automation->id)->count(),
            'Deux etapes differentes doivent produire deux executions, pas une seule.',
        );
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    private function opportunity(Company $company, string $stage): CrmOpportunity
=======
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
>>>>>>> origin/main
    {
        /** @var CrmOpportunity $opportunity */
        $opportunity = CrmOpportunity::query()->create([
            'company_id' => $company->id,
<<<<<<< HEAD
            'name' => 'Affaire '.$stage,
            'stage' => $stage,
            'status' => in_array($stage, ['won', 'lost'], true) ? 'closed' : 'open',
=======
            'name' => 'Affaire de test',
            'stage' => $stage,
            'status' => $status,
            'amount' => '1000.00',
            'currency' => 'DZD',
>>>>>>> origin/main
        ]);

        return $opportunity;
    }

<<<<<<< HEAD
    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('crm', true);
        $company->save();

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsManager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
=======
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
>>>>>>> origin/main
    }
}
