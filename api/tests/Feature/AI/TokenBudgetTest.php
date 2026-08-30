<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\Exceptions\TokenBudgetExceededException;
use App\AI\LLMClient;
use App\AI\Models\AIConversation;
use App\AI\TokenBudgetGuard;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-23-D10 (issue #6238) — budgets de tokens AI versionnés.
 *
 * Couvre le fail-closed des trois niveaux de budget (requête, contexte de
 * conversation, workflow d'agent) et l'observabilité p95 par workflow dans
 * l'analytics AI.
 */
class TokenBudgetTest extends TestCase
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

    public function test_guard_rejects_request_over_budget(): void
    {
        config(['ai.budgets.max_tokens_per_request' => 100]);

        $this->expectException(TokenBudgetExceededException::class);

        app(TokenBudgetGuard::class)->assertRequestWithinBudget(80, 40);
    }

    public function test_guard_accepts_request_within_budget(): void
    {
        config(['ai.budgets.max_tokens_per_request' => 100]);

        app(TokenBudgetGuard::class)->assertRequestWithinBudget(60, 39);

        $this->addToAssertionCount(1);
    }

    public function test_guard_detects_context_budget_exceeded(): void
    {
        config(['ai.budgets.max_context_tokens' => 200]);

        $guard = app(TokenBudgetGuard::class);

        $this->assertFalse($guard->contextBudgetExceeded(200));
        $this->assertTrue($guard->contextBudgetExceeded(201));
    }

    public function test_guard_rejects_workflow_over_budget(): void
    {
        config(['ai.budgets.max_tokens_per_workflow' => 50]);

        $this->expectException(TokenBudgetExceededException::class);

        app(TokenBudgetGuard::class)->assertWorkflowWithinBudget(51);
    }

    public function test_chat_rejects_request_over_budget_with_audit_error(): void
    {
        [$company, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithTokens(3_000, 2_000); // 5 000 > défaut 4 096

        $this->postJson('/api/v1/ai/chat', ['message' => 'Résume le mois'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_TOKEN_BUDGET_EXCEEDED');

        // Le rejet est tracé dans l'audit (observabilité errors) sans effet de bord.
        $this->assertDatabaseHas('ai_audit_logs', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
        ]);
        /** @var array<string, mixed> $logged */
        $logged = (array) DB::table('ai_audit_logs')
            ->where('company_id', $company->id)
            ->where('user_id', $employee->id)
            ->first();
        $error = $logged['error'] ?? null;
        $this->assertIsString($error);
        $this->assertStringContainsString('budget', $error);
    }

    public function test_chat_accepts_request_within_budget(): void
    {
        [, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithTokens(100, 50); // 150 < défaut 4 096

        $this->postJson('/api/v1/ai/chat', ['message' => 'Bonjour'])
            ->assertOk()
            ->assertJsonPath('data.tokens.input', 100)
            ->assertJsonPath('data.tokens.output', 50);

        $this->assertDatabaseCount('ai_conversations', 1);
    }

    public function test_chat_rejects_conversation_over_context_budget(): void
    {
        [$company, $employee] = $this->aiFixture();
        config(['ai.budgets.max_context_tokens' => 100]);

        $conversation = AIConversation::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'title' => 'Historique plein',
            'messages' => [],
            'context' => [],
            'token_count' => 150, // cumul > budget de contexte
        ]);

        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithTokens(10, 10);

        $this->postJson('/api/v1/ai/chat', [
            'message' => 'Continue',
            'conversation_id' => $conversation->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_TOKEN_BUDGET_EXCEEDED');
    }

    public function test_agent_run_rejects_workflow_over_budget(): void
    {
        [, $employee] = $this->aiFixture();
        config(['ai.budgets.max_tokens_per_workflow' => 30]);

        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithTokens(40, 0); // 40 > 30 dès la 1re étape

        $this->postJson('/api/v1/ai/agent/run', ['task' => 'Analyse mes absences'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_TOKEN_BUDGET_EXCEEDED');
    }

    public function test_usage_analytics_exposes_p95_and_workflow_breakdown(): void
    {
        [$company, $employee] = $this->aiFixture();

        // 10 requêtes de 100 à 1 000 tokens → p95 = 1 000.
        foreach (range(1, 10) as $i) {
            $this->insertAiAuditLog($company->id, $employee->id, [
                'input_tokens' => $i * 100,
                'output_tokens' => 0,
                'workflow' => $i % 2 === 0 ? 'agent_run' : null,
            ]);
        }

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/ai/analytics/usage')
            ->assertOk()
            ->assertJsonPath('data.0.company_id', $company->id)
            ->assertJsonPath('data.0.total_requests', 10)
            ->assertJsonPath('data.0.total_tokens', 5500)
            ->assertJsonPath('data.0.p95_tokens_per_request', 1000)
            ->assertJsonPath('data.0.workflows.0.workflow', 'agent_run')
            ->assertJsonPath('data.0.workflows.0.requests', 5)
            ->assertJsonPath('data.0.workflows.0.total_tokens', 3000);
    }

    public function test_conversation_token_count_is_cumulative(): void
    {
        [$company, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithTokens(100, 20);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Premier message'])->assertOk();

        $conversation = AIConversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame(120, (int) $conversation->token_count);

        $this->postJson('/api/v1/ai/chat', [
            'message' => 'Deuxième message',
            'conversation_id' => $conversation->id,
        ])->assertOk();

        $this->assertSame(240, (int) $conversation->refresh()->token_count);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function aiFixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $employee];
    }

    private function fakeLlmClientWithTokens(int $inputTokens, int $outputTokens): void
    {
        $this->app->instance(LLMClient::class, new class($inputTokens, $outputTokens) implements LLMClient
        {
            public function __construct(
                private readonly int $inputTokens,
                private readonly int $outputTokens,
            ) {}

            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(
                    content: 'Réponse simulée.',
                    inputTokens: $this->inputTokens,
                    outputTokens: $this->outputTokens,
                    model: 'test-model',
                );
            }

            public function provider(): string
            {
                return 'test';
            }
        });
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
            'workflow' => null,
            'created_at' => now(),
        ], $overrides));
    }
}
