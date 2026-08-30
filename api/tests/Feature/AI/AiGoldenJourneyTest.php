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
use App\Modules\Planning\Domain\Models\AbsenceType;
use Database\Seeders\AiPilotSeeder;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-23-D12 (issue #6241) — golden journey IA.
 *
 * Parcours end-to-end chat → action (pending) → confirmation humaine → effet
 * métier → audit complet, sur des données 100 % synthétiques ; vérifie aussi
 * le seed pilote IA (déterministe, réentrant) et le registre des golden
 * journeys (MAT-013).
 */
class AiGoldenJourneyTest extends TestCase
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

    public function test_golden_journey_chat_confirmation_effect_audit(): void
    {
        [$company, $manager] = $this->aiFixture();
        $type = $this->seedAbsenceType($company->id);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);

        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithWriteToolCall();

        // 1. Chat → action en attente de confirmation (jamais exécutée).
        $chat = $this->postJson('/api/v1/ai/chat', ['message' => 'Demande un conge du 10 au 12 juin'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.0.status', 'confirmation_required')
            ->json('data');

        $pendingId = $chat['pending_confirmations'][0]['pending_action_id'];
        $conversationId = $chat['conversation_id'];
        $this->assertDatabaseCount('absences', 0);

        // 2. Confirmation humaine → effet métier.
        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'create_absence');

        $this->assertDatabaseHas('absences', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'status' => 'pending',
        ]);

        // 3. Traçabilité complète : l'échange chat est consigné dans l'audit
        // (corrélable via conversation_id).
        $this->assertDatabaseHas('ai_audit_logs', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'conversation_id' => $conversationId,
        ]);
        $audit = DB::table('ai_audit_logs')
            ->where('company_id', $company->id)
            ->where('conversation_id', $conversationId)
            ->first();
        $this->assertStringContainsString('conge', (string) $audit->prompt);
    }

    public function test_pilot_seed_is_deterministic_reentrant_and_synthetic(): void
    {
        $this->seed(AiPilotSeeder::class);

        $company = Company::query()->where('slug', 'ai-pilot-001')->first();
        $this->assertNotNull($company);

        $this->assertDatabaseHas('employees', [
            'company_id' => $company->id,
            'email' => 'principal@ai-pilot-001.leopardo.test',
            'role' => 'manager',
        ]);
        $this->assertDatabaseHas('employees', [
            'company_id' => $company->id,
            'email' => 'employe@ai-pilot-001.leopardo.test',
        ]);
        $this->assertDatabaseHas('ai_conversations', [
            'company_id' => $company->id,
            'title' => 'Parcours pilote IA',
        ]);
        $this->assertDatabaseHas('ai_audit_logs', [
            'company_id' => $company->id,
            'model' => 'pilot-synthetic',
        ]);

        $toolCount = AIToolRegistryEntry::query()->count();
        $this->assertGreaterThan(0, $toolCount);

        // Réentrant : un second passage ne duplique ni tenant ni données.
        $this->seed(AiPilotSeeder::class);
        $this->assertSame(1, Company::query()->where('slug', 'ai-pilot-001')->count());
        $this->assertSame(1, DB::table('ai_conversations')->where('company_id', $company->id)->count());
    }

    public function test_golden_journey_registry_contains_bc23_ai_journey(): void
    {
        $registry = json_decode(
            file_get_contents(base_path('../dev-hub/tools/golden-journeys.json')),
            true,
        );

        $this->assertSame('active', $registry['solutions']['bc23_ai']['status'] ?? null);

        $journey = collect($registry['journeys'])->firstWhere('id', 'GJ-08');
        $this->assertNotNull($journey);
        $this->assertSame('bc23_ai', $journey['solution']);

        $routes = array_map(static fn (array $step): string => $step['route'], $journey['steps']);
        $this->assertContains('/api/v1/ai/chat', $routes);
        $this->assertContains('/api/v1/ai/actions/{pendingActionId}/confirm', $routes);
        $this->assertContains('/api/v1/ai/analytics/usage', $routes);
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

    private function fakeLlmClientWithWriteToolCall(): void
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
}
