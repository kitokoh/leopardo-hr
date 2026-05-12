<?php

namespace Tests\Feature;

use App\Models\AIConversation;
use App\Models\AIToolRegistryEntry;
use App\Models\Company;
use App\Models\Employee;
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
}
