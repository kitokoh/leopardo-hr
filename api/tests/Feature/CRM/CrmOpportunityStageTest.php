<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Enums\CrmAutomationTrigger;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmAutomation;
use App\Modules\CRM\Domain\Models\CrmAutomationRun;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
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
 */
class CrmOpportunityStageTest extends TestCase
{
    use RefreshTenantDatabase;

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
    {
        /** @var CrmOpportunity $opportunity */
        $opportunity = CrmOpportunity::query()->create([
            'company_id' => $company->id,
            'name' => 'Affaire '.$stage,
            'stage' => $stage,
            'status' => in_array($stage, ['won', 'lost'], true) ? 'closed' : 'open',
        ]);

        return $opportunity;
    }

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
    }
}
