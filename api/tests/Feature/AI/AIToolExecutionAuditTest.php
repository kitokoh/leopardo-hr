<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use App\AI\Models\AIToolRegistryEntry;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * A5 (#6852) — audit conversation → action → effet avec marqueur
 * « via assistant IA ».
 *
 * `ai_tool_executions` doit rendre rejouable la chaîne complète :
 *  - une commande LECTURE exécutée via le chat est journalisée avec sa
 *    conversation (source = 'assistant') ;
 *  - une commande ÉCRITURE sensible produit une ligne de proposition
 *    (stage confirmation_required, pending_action_id) puis, après
 *    confirmation humaine, une ligne d'exécution reliée par le MÊME
 *    pending_action_id ;
 *  - un refus humain est tracé (stage rejected) sans effet métier.
 */
class AIToolExecutionAuditTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_read_tool_execution_is_logged_with_conversation_and_source_assistant(): void
    {
        [$company, $manager] = $this->aiFixture();
        $this->seedTeam($company->id);
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
        Sanctum::actingAs($manager);
        $this->fakeLlmWithReadToolCall();

        $chat = $this->postJson('/api/v1/ai/chat', ['message' => 'Donne-moi une vue de mon equipe'])
            ->assertOk()
            ->json('data');

        $conversationId = $chat['conversation_id'];

        $this->assertDatabaseHas('ai_tool_executions', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'conversation_id' => $conversationId,
            'tool_name' => 'team_overview',
            'stage' => 'executed',
            'success' => true,
            'source' => 'assistant',
        ]);

        $row = DB::table('ai_tool_executions')
            ->where('company_id', $company->id)
            ->where('conversation_id', $conversationId)
            ->where('tool_name', 'team_overview')
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->result_summary, 'Le résultat de l\'outil doit être résumé.');
        $this->assertSame('assistant', $row->source);

        // L'échange reste aussi consigné dans l'audit conversationnel existant.
        $this->assertDatabaseHas('ai_audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'conversation_id' => $conversationId,
        ]);
    }

    public function test_write_confirmation_chain_links_proposal_and_execution(): void
    {
        [$company, $manager] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);
        Sanctum::actingAs($manager);
        $this->fakeLlmWithWriteToolCall();

        // 1. Chat → proposition d'écriture (jamais exécutée), journalisée.
        $chat = $this->postJson('/api/v1/ai/chat', ['message' => 'Cree une absence du 10 au 12 juin'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.0.status', 'confirmation_required')
            ->json('data');

        $pendingId = $chat['pending_confirmations'][0]['pending_action_id'];
        $conversationId = $chat['conversation_id'];
        $this->assertDatabaseCount('absences', 0);

        $this->assertDatabaseHas('ai_tool_executions', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'conversation_id' => $conversationId,
            'pending_action_id' => $pendingId,
            'tool_name' => 'create_absence',
            'stage' => 'confirmation_required',
            'success' => true,
            'source' => 'assistant',
        ]);

        // Sanitisation A5/A6 : les dates ISO et l'UUID (faux positifs de la
        // règle « téléphone ») ne sont PAS masqués — le journal reste
        // exploitable ; tool_input est stocké en JSON.
        $proposal = DB::table('ai_tool_executions')
            ->where('company_id', $company->id)
            ->where('pending_action_id', $pendingId)
            ->first();
        $this->assertNotNull($proposal);
        $input = json_decode((string) $proposal->tool_input, true);
        $this->assertIsArray($input);
        $this->assertSame('2026-06-10', $input['start_date'] ?? null);
        $this->assertSame('2026-06-12', $input['end_date'] ?? null);
        $this->assertStringContainsString('2026-06-10', (string) $proposal->result_summary);
        $this->assertStringNotContainsString('[téléphone]', (string) $proposal->result_summary);

        // 2. Confirmation humaine → exécution, reliée au même pending_action_id.
        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('absences', [
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'status' => 'pending',
        ]);

        $execution = DB::table('ai_tool_executions')
            ->where('company_id', $company->id)
            ->where('pending_action_id', $pendingId)
            ->where('stage', 'executed')
            ->first();
        $this->assertNotNull($execution, 'L\'exécution confirmée doit être journalisée.');
        $this->assertSame(1, (int) $execution->success);
        $this->assertSame('assistant', $execution->source);
        $this->assertNotNull($execution->result_summary);

        // 3. Chaîne rejouable : 2 lignes (proposition + exécution) partagent le
        // pending_action_id ; la proposition porte la conversation.
        $chain = DB::table('ai_tool_executions')
            ->where('company_id', $company->id)
            ->where('pending_action_id', $pendingId)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $chain);
        $proposalRow = $chain[0] ?? null;
        $executionRow = $chain[1] ?? null;
        $this->assertNotNull($proposalRow, 'La proposition doit être journalisée.');
        $this->assertNotNull($executionRow, "L'exécution confirmée doit être journalisée.");
        $this->assertSame('confirmation_required', $proposalRow->stage);
        $this->assertSame('executed', $executionRow->stage);
        $this->assertSame($conversationId, (int) $proposalRow->conversation_id);
    }

    public function test_rejected_write_is_logged_without_business_effect(): void
    {
        [$company, $manager] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);
        Sanctum::actingAs($manager);
        $this->fakeLlmWithWriteToolCall();

        $chat = $this->postJson('/api/v1/ai/chat', ['message' => 'Cree une absence du 10 au 12 juin'])
            ->assertOk()
            ->json('data');
        $pendingId = $chat['pending_confirmations'][0]['pending_action_id'];

        $this->postJson("/api/v1/ai/actions/{$pendingId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseCount('absences', 0);
        $this->assertDatabaseHas('ai_tool_executions', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'pending_action_id' => $pendingId,
            'tool_name' => 'create_absence',
            'stage' => 'rejected',
            'source' => 'assistant',
        ]);
    }

    /**
     * @return array{Company, Employee}
     */
    private function aiFixture(): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'contract_type' => 'CDI',
            'status' => 'active',
        ]);

        return [$company, $employee];
    }

    private function seedTeam(string $companyId): void
    {
        /** @var Department $dept */
        $dept = Department::create(['name' => 'Operations']);

        Employee::factory()->create([
            'company_id' => $companyId,
            'department_id' => $dept->id,
            'contract_type' => 'CDI',
            'status' => 'active',
        ]);
    }

    private function seedAbsenceType(string $companyId): AbsenceType
    {
        return AbsenceType::create([
            'company_id' => $companyId,
            'name' => 'Conges payes',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
    }

    private function registerWriteTool(string $name): void
    {
        AIToolRegistryEntry::create([
            'name' => $name,
            'description' => "Write tool {$name}",
            'parameters' => ['type' => 'object', 'properties' => new stdClass],
            'required_permissions' => [],
            'required_role' => 'manager',
            'module' => 'rh',
            'active' => true,
        ]);
    }

    private function fakeLlmWithWriteToolCall(): void
    {
        $this->app->instance(LLMClient::class, new class implements LLMClient
        {
            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(
                    content: 'Je prepare la demande.',
                    toolCalls: [
                        new ToolCall('call_1', 'create_absence', [
                            'start_date' => '2026-06-10',
                            'end_date' => '2026-06-12',
                            'reason' => 'Conges',
                        ]),
                    ],
                    inputTokens: 5,
                    outputTokens: 8,
                    model: 'test-model',
                );
            }

            public function provider(): string
            {
                return 'test';
            }
        });
    }

    private function fakeLlmWithReadToolCall(): void
    {
        $this->app->instance(LLMClient::class, new class implements LLMClient
        {
            private int $calls = 0;

            public function chat(array $messages, array $tools = []): AIResponse
            {
                $this->calls++;

                if ($this->calls === 1) {
                    return new AIResponse(
                        content: '',
                        toolCalls: [new ToolCall('call_1', 'team_overview', [])],
                        inputTokens: 5,
                        outputTokens: 8,
                        model: 'test-model',
                    );
                }

                return new AIResponse(
                    content: 'Voici la vue de votre equipe.',
                    inputTokens: 3,
                    outputTokens: 6,
                    model: 'test-model',
                );
            }

            public function provider(): string
            {
                return 'test';
            }
        });
    }
}
