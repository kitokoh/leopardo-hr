<?php

namespace Tests\Feature;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;
use App\Models\AIConversation;
use App\Models\AIToolRegistryEntry;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AIGatewayAndAnalyticsTest extends TestCase
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

    public function test_ai_tools_returns_only_active_tools(): void
    {
        [$company, $employee] = $this->aiFixture();

        AIToolRegistryEntry::create([
            'name' => 'attendance_today',
            'description' => 'Read today attendance.',
            'parameters' => [],
            'required_permissions' => [],
            'required_role' => 'manager',
            'module' => 'attendance',
            'active' => true,
        ]);
        AIToolRegistryEntry::create([
            'name' => 'disabled_tool',
            'description' => 'Disabled.',
            'parameters' => [],
            'required_permissions' => [],
            'required_role' => 'manager',
            'module' => 'attendance',
            'active' => false,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/ai/tools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'attendance_today');
    }

    public function test_ai_history_and_delete_are_scoped_to_authenticated_user_and_company(): void
    {
        [$company, $employee] = $this->aiFixture();
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);

        $ownConversation = AIConversation::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'title' => 'Own',
            'messages' => [],
            'context' => [],
            'token_count' => 42,
        ]);
        AIConversation::create([
            'company_id' => $company->id,
            'user_id' => $otherEmployee->id,
            'title' => 'Other user',
            'messages' => [],
            'context' => [],
        ]);
        AIConversation::create([
            'company_id' => $otherCompany->id,
            'user_id' => $employee->id,
            'title' => 'Other company',
            'messages' => [],
            'context' => [],
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/ai/chat/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Own');

        $this->deleteJson("/api/v1/ai/chat/{$ownConversation->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Conversation deleted.');
        $this->assertDatabaseMissing('ai_conversations', ['id' => $ownConversation->id]);
    }

    public function test_ai_analytics_use_real_audit_columns_and_do_not_leak_other_companies(): void
    {
        [$company, $employee] = $this->aiFixture();
        $otherCompany = Company::factory()->create();

        $this->insertAiAuditLog($company->id, $employee->id, [
            'tools_called' => ['attendance_today'],
            'input_tokens' => 100,
            'output_tokens' => 25,
            'cost_cents' => 7,
            'duration_ms' => 120,
        ]);
        $this->insertAiAuditLog($company->id, $employee->id, [
            'tools_called' => ['attendance_today', 'absence_list'],
            'input_tokens' => 40,
            'output_tokens' => 10,
            'cost_cents' => 3,
            'duration_ms' => 80,
            'error' => 'Provider timeout',
        ]);
        $this->insertAiAuditLog($otherCompany->id, $employee->id, [
            'tools_called' => ['foreign_tool'],
            'input_tokens' => 999,
            'output_tokens' => 999,
            'cost_cents' => 999,
            'duration_ms' => 999,
            'error' => 'Foreign error',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/ai/analytics/usage')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_id', $company->id)
            ->assertJsonPath('data.0.total_requests', 2)
            ->assertJsonPath('data.0.total_tokens', 175)
            ->assertJsonPath('data.0.total_cost_cents', 10);

        $this->getJson('/api/v1/ai/analytics/tools')
            ->assertOk()
            ->assertJsonPath('data.0.tool_called', 'attendance_today')
            ->assertJsonPath('data.0.call_count', 2);

        $this->getJson('/api/v1/ai/analytics/errors')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 2)
            ->assertJsonPath('data.total_errors', 1)
            ->assertJsonPath('data.recent_errors.0.error', 'Provider timeout');
    }

    public function test_ai_analytics_are_restricted_to_principal_or_rh_managers(): void
    {
        [$company, $principal] = $this->aiFixture();
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $departmentManager = Employee::factory()->managerDept()->create(['company_id' => $company->id]);

        $this->insertAiAuditLog($company->id, $principal->id, [
            'tools_called' => ['attendance_today'],
            'input_tokens' => 10,
            'output_tokens' => 5,
            'cost_cents' => 1,
        ]);

        Sanctum::actingAs($principal);
        $this->getJson('/api/v1/ai/analytics/usage')->assertOk();

        Sanctum::actingAs($rh);
        $this->getJson('/api/v1/ai/analytics/usage')->assertOk();

        Sanctum::actingAs($departmentManager);
        $this->getJson('/api/v1/ai/analytics/usage')->assertForbidden();
    }

    public function test_voice_transcribe_and_synthesize_validate_without_external_provider_keys(): void
    {
        [, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);

        config([
            'ai.voice.stt_provider' => 'whisper',
            'ai.providers.openai.key' => null,
            'ai.voice.tts_provider' => 'elevenlabs',
            'ai.voice.elevenlabs_key' => null,
        ]);

        $this->post('/api/v1/ai/voice/transcribe', [
            'audio' => UploadedFile::fake()->create('voice.mp3', 12, 'audio/mpeg'),
            'language' => 'fr',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.text', '')
            ->assertJsonPath('data.language', 'fr')
            ->assertJsonPath('data.provider', 'whisper');

        $this->postJson('/api/v1/ai/voice/synthesize', [
            'text' => 'Bonjour',
            'language' => 'fr',
        ])
            ->assertOk()
            ->assertJsonPath('data.audio_url', null)
            ->assertJsonPath('data.provider', 'elevenlabs');
    }

    public function test_voice_command_uses_orchestrator_contract_and_returns_conversation_context(): void
    {
        [$company, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);
        $this->fakeLlmClient('Commande vocale traitee.');

        config([
            'ai.voice.stt_provider' => 'whisper',
            'ai.providers.openai.key' => null,
            'ai.voice.tts_provider' => 'elevenlabs',
            'ai.voice.elevenlabs_key' => null,
        ]);

        $this->post('/api/v1/ai/voice/command', [
            'audio' => UploadedFile::fake()->create('voice.mp3', 12, 'audio/mpeg'),
            'language' => 'fr',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.transcribed_text', '')
            ->assertJsonPath('data.ai_response', 'Commande vocale traitee.')
            ->assertJsonPath('data.language', 'fr')
            ->assertJsonStructure(['data' => ['conversation_id', 'audio_url']]);

        $this->assertDatabaseHas('ai_audit_logs', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'response' => 'Commande vocale traitee.',
        ]);
    }

    public function test_agent_workflows_and_run_endpoint_are_bounded_by_validation(): void
    {
        [, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);
        $this->fakeLlmClient('Plan agent pret.');

        $this->getJson('/api/v1/ai/agent/workflows')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', 'prepare_payroll');

        $this->postJson('/api/v1/ai/agent/run', [
            'task' => 'Prepare le rapport hebdomadaire.',
            'max_steps' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.task', 'Prepare le rapport hebdomadaire.')
            ->assertJsonPath('data.total_steps', 1)
            ->assertJsonPath('data.final_response', 'Plan agent pret.');

        $this->postJson('/api/v1/ai/agent/run', [
            'task' => 'Trop loin',
            'max_steps' => 21,
        ])->assertUnprocessable();
    }

    public function test_ai_analytics_costs_support_period_grouping(): void
    {
        [$company, $employee] = $this->aiFixture();

        $this->insertAiAuditLog($company->id, $employee->id, [
            'provider' => 'openai',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cost_cents' => 12,
            'created_at' => '2026-05-05 10:00:00',
        ]);
        $this->insertAiAuditLog($company->id, $employee->id, [
            'provider' => 'openai',
            'input_tokens' => 40,
            'output_tokens' => 10,
            'cost_cents' => 3,
            'created_at' => '2026-05-12 10:00:00',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/ai/analytics/costs?from=2026-05-01&to=2026-05-31&group_by=month')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', '2026-05')
            ->assertJsonPath('data.0.provider', 'openai')
            ->assertJsonPath('data.0.total_cost_cents', 15)
            ->assertJsonPath('data.0.total_tokens', 200);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function aiFixture(): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $employee];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertAiAuditLog(string $companyId, int $userId, array $overrides = []): void
    {
        DB::table('ai_audit_logs')->insert(array_merge([
            'company_id' => $companyId,
            'user_id' => $userId,
            'prompt' => 'Question',
            'response' => 'Answer',
            'tools_called' => json_encode([]),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_cents' => 0,
            'duration_ms' => 0,
            'error' => null,
            'created_at' => now(),
        ], [
            ...$overrides,
            'tools_called' => json_encode($overrides['tools_called'] ?? []),
        ]));
    }

    private function fakeLlmClient(string $content): void
    {
        $this->app->instance(LLMClient::class, new class($content) implements LLMClient
        {
            public function __construct(private readonly string $content) {}

            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(
                    content: $this->content,
                    inputTokens: 7,
                    outputTokens: 11,
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
