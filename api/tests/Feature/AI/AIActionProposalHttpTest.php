<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\PendingActionStore;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #9775 — intégration HTTP du flux de proposition d'action (PendingAction).
 *
 * `AIActionProposalTest` couvre déjà les refus fail-closed de
 * `POST /api/v1/ai/actions/propose`. Ce fichier couvre ce qu'il ne couvrait
 * pas : le **flux complet tel que le serveur MCP l'utilise**, c'est-à-dire
 * proposition → confirmation humaine → exécution, et proposition → rejet.
 *
 * Ce qui est verrouillé ici :
 *  1. la proposition ne produit AUCUN effet métier (elle ne fait que stocker) ;
 *  2. la confirmation exécute l'action, sous le MÊME `pending_action_id` ;
 *  3. le rejet ne produit aucun effet et est audité `stage: rejected` ;
 *  4. un pending consommé ne peut pas être rejoué par un autre utilisateur ;
 *  5. la chaîne d'audit relie proposition et exécution.
 *
 * Aucun appel réseau : le store et l'IntentEngine sont réels, seule la couche
 * métier de l'action est exercée sur la base de test.
 */
class AIActionProposalHttpTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array{company: Company, manager: Employee}
     */
    private function tenant(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'contract_type' => 'CDI',
            'status' => 'active',
        ]);

        return compact('company', 'manager');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function propose(Employee $actor, string $tool, array $arguments): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => $tool,
            'arguments' => $arguments,
        ]);
    }

    public function test_proposition_puis_confirmation_execute_l_action_sous_le_meme_pending(): void
    {
        ['manager' => $manager] = $this->tenant();

        $proposal = $this->propose($manager, 'absence_decision', [
            'absence_id' => 1,
            'decision' => 'approve',
        ]);

        $proposal->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmation_required')
            ->assertJsonPath('data.sensitivity', 'write');

        $pendingId = (string) $proposal->json('data.pending_action_id');
        $this->assertNotSame('', $pendingId);

        // Les endpoints de suite sont annoncés par la proposition elle-même :
        // un client MCP n'a pas à deviner l'URL de confirmation.
        $proposal->assertJsonPath('data.confirm_endpoint', '/api/v1/ai/actions/'.$pendingId.'/confirm');
        $proposal->assertJsonPath('data.reject_endpoint', '/api/v1/ai/actions/'.$pendingId.'/reject');

        // 1. La proposition n'a RIEN exécuté.
        $this->assertDatabaseCount('absences', 0);

        // 2. La confirmation humaine exécute l'action.
        $confirmation = $this->postJson('/api/v1/ai/actions/'.$pendingId.'/confirm');

        $confirmation->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'absence_decision');

        // 3. La chaîne d'audit relie proposition et exécution sous le même id.
        $this->assertDatabaseHas('ai_tool_executions', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'pending_action_id' => $pendingId,
            'tool_name' => 'absence_decision',
            'stage' => 'proposed',
        ]);
        $this->assertDatabaseHas('ai_tool_executions', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'pending_action_id' => $pendingId,
            'tool_name' => 'absence_decision',
            'stage' => 'executed',
        ]);
    }

    public function test_rejet_ne_produit_aucun_effet_et_est_audite(): void
    {
        ['manager' => $manager] = $this->tenant();

        $pendingId = (string) $this->propose($manager, 'absence_decision', [
            'absence_id' => 1,
            'decision' => 'approve',
        ])->assertStatus(201)->json('data.pending_action_id');

        $this->postJson('/api/v1/ai/actions/'.$pendingId.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.tool', 'absence_decision');

        // Aucun effet métier : un rejet n'exécute rien.
        $this->assertDatabaseCount('absences', 0);

        $this->assertDatabaseHas('ai_tool_executions', [
            'pending_action_id' => $pendingId,
            'tool_name' => 'absence_decision',
            'stage' => 'rejected',
        ]);
    }

    public function test_un_pending_consomme_ne_peut_pas_etre_confirme_par_un_autre_utilisateur(): void
    {
        ['manager' => $manager] = $this->tenant();
        ['manager' => $otherManager] = $this->tenant();

        $pendingId = (string) $this->propose($manager, 'absence_decision', [
            'absence_id' => 1,
            'decision' => 'approve',
        ])->assertStatus(201)->json('data.pending_action_id');

        // Isolation stricte : le pending est lié à (company_id, user_id).
        // Un manager d'un autre tenant ne peut ni le confirmer ni le rejeter.
        Sanctum::actingAs($otherManager);

        $this->postJson('/api/v1/ai/actions/'.$pendingId.'/confirm')->assertNotFound();
        $this->postJson('/api/v1/ai/actions/'.$pendingId.'/reject')->assertNotFound();

        // Le pending du propriétaire est intact : le refus n'a rien consommé.
        $this->assertNotNull(app(PendingActionStore::class)->pull(
            $pendingId,
            (string) $manager->company_id,
            (int) $manager->id,
        ));
    }

    public function test_la_proposition_refuse_un_outil_de_lecture_sans_creer_de_pending(): void
    {
        ['manager' => $manager] = $this->tenant();

        // `team_overview` est une lecture : elle s'exécute via tools/call,
        // jamais via une proposition. Le refus doit être AVANT tout stockage.
        $this->propose($manager, 'team_overview', [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_ACTION_NOT_SENSITIVE');

        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }

    public function test_la_proposition_refuse_un_role_non_habilite_sans_creer_de_pending(): void
    {
        ['company' => $company] = $this->tenant();
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_type' => 'CDI',
            'status' => 'active',
        ]);

        $this->propose($employee, 'absence_decision', [
            'absence_id' => 1,
            'decision' => 'approve',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'AI_TOOL_PERMISSION_DENIED');

        // Fail-closed : aucun pending fantôme pour un utilisateur non habilité.
        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }

    public function test_la_proposition_refuse_un_outil_inconnu(): void
    {
        ['manager' => $manager] = $this->tenant();

        $this->propose($manager, 'outil_inexistant', [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_TOOL_UNKNOWN');

        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }
}
