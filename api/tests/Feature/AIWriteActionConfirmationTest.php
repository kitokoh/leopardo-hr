<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\IntentEngine;
use App\AI\LLMClient;
use App\AI\PendingActionStore;
use App\AI\ToolRegistry;
use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\AIToolRegistryEntry;
use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AIWriteActionConfirmationTest extends TestCase
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

    public function test_write_tool_returns_confirmation_required_without_creating_absence(): void
    {
        [$company, $employee] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'create_absence', [
                    'start_date' => '2026-06-10',
                    'end_date' => '2026-06-12',
                    'reason' => 'Conges',
                ]),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $employee->id);
        $payload = json_decode($results[0]->content, true);

        $this->assertTrue($results[0]->success);
        $this->assertSame('confirmation_required', $payload['status'] ?? null);
        $this->assertNotEmpty($payload['pending_action_id'] ?? null);
        $this->assertDatabaseCount('absences', 0);
    }

    public function test_confirm_action_executes_create_absence(): void
    {
        [$company, $employee] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        Sanctum::actingAs($employee);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employee->id,
            'create_absence',
            [
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-11',
                'reason' => 'Conges',
            ],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'create_absence')
            ->assertJsonPath('data.result.status', 'pending');

        $this->assertDatabaseHas('absences', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);
    }

    public function test_reject_action_does_not_create_absence(): void
    {
        [$company, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employee->id,
            'create_absence',
            [
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-11',
            ],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.tool', 'create_absence');

        $this->assertDatabaseCount('absences', 0);
    }

    public function test_confirm_action_is_scoped_to_authenticated_user(): void
    {
        [$company, $employee] = $this->aiFixture();
        $other = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($other);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employee->id,
            'create_absence',
            ['start_date' => '2026-06-10', 'end_date' => '2026-06-11'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")->assertNotFound();
        $this->assertDatabaseCount('absences', 0);
    }

    public function test_confirm_approve_absence_updates_status(): void
    {
        [$company, $manager] = $this->aiFixture();
        $type = $this->seedAbsenceType($company->id);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        $absence = Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'days_count' => 2,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'approve_absence',
            ['absence_id' => $absence->id],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.result.status', 'approved');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);
    }

    public function test_orchestrator_returns_pending_confirmations_for_write_tools(): void
    {
        [$company, $employee] = $this->aiFixture();
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);
        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithWriteToolCall();

        $this->postJson('/api/v1/ai/chat', ['message' => 'Demande un conge'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.0.status', 'confirmation_required')
            ->assertJsonPath('data.pending_confirmations.0.tool', 'create_absence');

        $this->assertDatabaseCount('absences', 0);
    }

    private function fakeLlmClientWithWriteToolCall(): void
    {
        $this->app->instance(LLMClient::class, new class implements LLMClient {
            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(
                    content: 'Je prepare la demande.',
                    toolCalls: [
                        new ToolCall('call_1', 'create_absence', [
                            'start_date' => '2026-06-10',
                            'end_date' => '2026-06-12',
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
}
