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
 * Issue #9753 — proposition d'action depuis un client MCP externe.
 *
 * `POST /api/v1/ai/actions/propose` est le pont manquant entre le serveur MCP
 * (qui n'a AUCUN accès base) et le flux `PendingAction` de Laravel. Il crée une
 * PROPOSITION, jamais une exécution : l'effet métier n'a lieu qu'après
 * confirmation humaine.
 *
 * Ces tests verrouillent les quatre refus fail-closed (outil inconnu, outil de
 * lecture, outil sans handler, RBAC) et l'isolation tenant du pending créé.
 */
class AIActionProposalTest extends TestCase
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
     * @return array{company: Company, manager: Employee, employee: Employee}
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
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_type' => 'CDI',
            'status' => 'active',
        ]);

        return compact('company', 'manager', 'employee');
    }

    public function test_propose_une_action_sensible_cree_un_pending(): void
    {
        ['manager' => $manager] = $this->tenant();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => 'absence_decision',
            'arguments' => ['absence_id' => 1, 'decision' => 'approve'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmation_required')
            ->assertJsonPath('data.tool', 'absence_decision')
            ->assertJsonPath('data.sensitivity', 'write');

        $pendingId = $response->json('data.pending_action_id');
        $this->assertIsString($pendingId);
        $this->assertNotSame('', $pendingId);

        // Le pending est bien stocké et rattaché au bon tenant/utilisateur.
        $pending = app(PendingActionStore::class)->pull(
            (string) $pendingId,
            (string) $manager->company_id,
            (int) $manager->id,
        );
        $this->assertNotNull($pending);
        $this->assertSame('absence_decision', $pending['tool']);
        $this->assertSame(['absence_id' => 1, 'decision' => 'approve'], $pending['arguments']);

        // Aucun effet métier : la proposition n'exécute rien.
        $this->assertDatabaseCount('absences', 0);

        // Audit : la proposition est tracée sous le même pending_action_id.
        $this->assertDatabaseHas('ai_tool_executions', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'pending_action_id' => $pendingId,
            'tool_name' => 'absence_decision',
            'stage' => 'proposed',
            'success' => true,
        ]);
    }

    public function test_refuse_un_outil_inconnu_sans_creer_de_pending(): void
    {
        ['manager' => $manager] = $this->tenant();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => 'outil_qui_nexiste_pas',
            'arguments' => [],
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'AI_TOOL_UNKNOWN');
        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }

    public function test_refuse_un_outil_de_lecture(): void
    {
        ['manager' => $manager] = $this->tenant();
        Sanctum::actingAs($manager);

        // `team_overview` est une lecture : elle s'exécute directement via
        // tools/call, une confirmation n'aurait aucun sens.
        $response = $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => 'team_overview',
            'arguments' => [],
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'AI_ACTION_NOT_SENSITIVE');
        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }

    public function test_refuse_un_outil_sans_handler_de_confirmation(): void
    {
        ['manager' => $manager] = $this->tenant();
        Sanctum::actingAs($manager);

        // Sans handler, `executeConfirmedWrite()` refuserait à la confirmation :
        // créer un pending serait une promesse fantôme.
        config(['ai.write_tools' => []]);

        $response = $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => 'absence_decision',
            'arguments' => ['absence_id' => 1, 'decision' => 'approve'],
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'AI_ACTION_NOT_CONFIRMABLE');
        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }

    public function test_refuse_un_role_non_habilite_sans_creer_de_pending(): void
    {
        ['employee' => $employee] = $this->tenant();
        Sanctum::actingAs($employee);

        // `absence_decision` exige le rôle manager : un employé est refusé
        // AVANT tout stockage (fail-closed, aucun pending fantôme).
        $response = $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => 'absence_decision',
            'arguments' => ['absence_id' => 1, 'decision' => 'approve'],
        ]);

        $response->assertStatus(403)->assertJsonPath('error', 'AI_TOOL_PERMISSION_DENIED');
        $this->assertSame(0, DB::table('ai_pending_actions')->count());
    }

    public function test_un_pending_est_invisible_depuis_un_autre_tenant(): void
    {
        ['manager' => $manager] = $this->tenant();
        ['manager' => $otherManager] = $this->tenant();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/ai/actions/propose', [
            'tool' => 'absence_decision',
            'arguments' => ['absence_id' => 1, 'decision' => 'approve'],
        ]);

        $pendingId = (string) $response->json('data.pending_action_id');

        // Isolation stricte : company_id ET user_id doivent correspondre.
        $this->assertNull(app(PendingActionStore::class)->pull(
            $pendingId,
            (string) $otherManager->company_id,
            (int) $otherManager->id,
        ));
    }

    public function test_valide_les_parametres_requis(): void
    {
        ['manager' => $manager] = $this->tenant();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/ai/actions/propose', ['arguments' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tool']);
    }
}
