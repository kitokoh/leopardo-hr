<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\IntentEngine;
use App\AI\LLMClient;
use App\AI\Models\AIToolRegistryEntry;
use App\AI\ToolPermissionPolicy;
use App\AI\WriteActionRunner;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use Database\Seeders\AIToolRegistrySeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-23-D05 (issue #6237) — matrice de permissions par outil AI versionnée.
 *
 * Couvre l'enforcement à l'exécution (lecture ET écriture, y compris à la
 * confirmation), les tests négatifs par rôle, le filtrage de l'exposition
 * `/ai/tools` et la garde anti-dérive config ↔ registre.
 */
class ToolPermissionMatrixTest extends TestCase
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

    public function test_config_matrix_covers_every_active_registry_tool(): void
    {
        /** @var array<string, array{role: string, permissions: list<string>}> $matrix */
        $matrix = config('ai.tool_permissions', []);
        /** @var array<int, string> $active */
        $active = AIToolRegistryEntry::query()->where('active', true)->pluck('name')->all();

        $this->assertNotEmpty($active);
        foreach ($active as $tool) {
            $this->assertArrayHasKey(
                $tool,
                $matrix,
                "Outil actif '{$tool}' dans ai_tool_registry mais absent de la matrice config ai.tool_permissions"
            );
            $this->assertNotEmpty($matrix[$tool]['permissions'] ?? [], "Matrice '{$tool}' sans permissions requises");
        }
    }

    public function test_employee_cannot_approve_absence(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'approve_absence', ['absence_id' => 999]),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $employee->id);

        $this->assertFalse($results[0]->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($results[0]->content)['error'] ?? null);
    }

    public function test_manager_can_request_confirmation_for_approve_absence(): void
    {
        [$company, $manager] = $this->aiFixture(role: 'manager');

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'approve_absence', ['absence_id' => 1]),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $manager->id);

        $this->assertTrue($results[0]->success);
        $this->assertSame('confirmation_required', $this->payload($results[0]->content)['status'] ?? null);
    }

    public function test_employee_can_request_confirmation_for_own_absence(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'create_absence', [
                    'start_date' => '2026-06-10',
                    'end_date' => '2026-06-11',
                ]),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $employee->id);

        $this->assertTrue($results[0]->success);
        $this->assertSame('confirmation_required', $this->payload($results[0]->content)['status'] ?? null);
    }

    public function test_employee_cannot_read_manager_only_tool(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'get_headcount', []),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $employee->id);

        $this->assertFalse($results[0]->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($results[0]->content)['error'] ?? null);
    }

    public function test_manager_can_read_manager_tool(): void
    {
        [$company, $manager] = $this->aiFixture(role: 'manager');

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'get_headcount', []),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $manager->id);

        $this->assertTrue($results[0]->success);
        $this->assertArrayHasKey('total', $this->payload($results[0]->content));
    }

    public function test_confirmed_write_denied_returns_stable_error(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');

        $engine = app(IntentEngine::class);
        $result = $engine->executeConfirmedWrite(
            'approve_absence',
            ['absence_id' => 1],
            $company->id,
            $employee->id,
        );

        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $result['error'] ?? null);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $result['code'] ?? null);
    }

    public function test_tools_endpoint_is_filtered_by_role(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);
        /** @var array<int, array<string, mixed>> $employeeData */
        $employeeData = $this->getJson('/api/v1/ai/tools')->json('data');
        $employeeNames = collect($employeeData)->pluck('name')->all();
        $this->assertContains('create_absence', $employeeNames);
        $this->assertNotContains('approve_absence', $employeeNames);
        $this->assertNotContains('get_headcount', $employeeNames);

        /** @var Employee $manager */
        Sanctum::actingAs($manager);
        /** @var array<int, array<string, mixed>> $managerData */
        $managerData = $this->getJson('/api/v1/ai/tools')->json('data');
        $managerNames = collect($managerData)->pluck('name')->all();
        $this->assertContains('approve_absence', $managerNames);
        $this->assertContains('get_headcount', $managerNames);
    }

    public function test_chat_employee_denied_approve_absence_has_no_pending_confirmation(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');
        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithWriteToolCall();

        $this->postJson('/api/v1/ai/chat', ['message' => 'Approuve l absence 1'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations', [])
            ->assertJsonPath('data.tools_used.0', 'approve_absence');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function aiFixture(string $role): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = $role === 'manager'
            ? Employee::factory()->manager()->create(['company_id' => $company->id])
            : Employee::factory()->create(['company_id' => $company->id]);

        return [$company, $employee];
    }

    /**
     * Décode un ToolResult.content JSON (stdClass/mixed → tableau).
     *
     * @return array<string, mixed>
     */
    private function payload(string $content): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
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
                        new ToolCall('call_1', 'approve_absence', ['absence_id' => 1]),
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

    // ================= #6532 / #6533 (audit sécurité IA) =================

    public function test_employee_cannot_use_pii_read_tools(): void
    {
        $policy = app(ToolPermissionPolicy::class);
        [$company, $employee] = $this->aiFixture(role: 'employee');

        foreach (['get_employees', 'get_employee_details', 'search_employees', 'get_daily_summary'] as $tool) {
            $this->assertFalse(
                $policy->canUse($tool, 'employee'),
                "L'outil PII '{$tool}' doit être refusé au rôle employee (#6532)"
            );
        }

        // Le registre (exposition LLM) ne doit pas non plus proposer ces outils.
        $registry = app(ToolRegistry::class);
        $exposed = array_keys($registry->getToolsForRole('employee', (string) $company->id));
        foreach (['get_employees', 'get_employee_details', 'search_employees', 'get_daily_summary'] as $tool) {
            $this->assertNotContains($tool, $exposed, "L'outil '{$tool}' ne doit pas être exposé à un employee");
        }
    }

    public function test_manager_can_use_pii_read_tools(): void
    {
        $policy = app(ToolPermissionPolicy::class);

        foreach (['get_employees', 'get_employee_details', 'search_employees', 'get_daily_summary'] as $tool) {
            $this->assertTrue($policy->canUse($tool, 'manager'), "L'outil '{$tool}' doit être autorisé au manager");
        }
    }

    public function test_employee_cannot_create_absence_for_another_employee(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');
        $other = Employee::factory()->create(['company_id' => $company->id]);

        $runner = app(WriteActionRunner::class);
        $result = $runner->run('create_absence', [
            'employee_id' => $other->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ], (string) $company->id, $employee->id);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('PERMISSION_DENIED', (string) $result['error'], '#6533 : employee ne crée pas d\'absence pour autrui');
    }

    public function test_employee_can_create_absence_for_self(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');

        $runner = app(WriteActionRunner::class);
        $result = $runner->run('create_absence', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ], (string) $company->id, $employee->id);

        $this->assertArrayNotHasKey('error', $result, 'l\'employé crée sa propre absence');
        $this->assertSame($employee->id, $result['employee_id'] ?? null);
    }

    public function test_employee_cannot_approve_absence_via_runner(): void
    {
        [$company, $employee] = $this->aiFixture(role: 'employee');
        $requestor = Employee::factory()->create(['company_id' => $company->id]);
        $absence = Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $requestor->id,
            'status' => 'pending',
        ]);

        $runner = app(WriteActionRunner::class);
        $result = $runner->run('approve_absence', [
            'absence_id' => $absence->id,
        ], (string) $company->id, $employee->id);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('PERMISSION_DENIED', (string) $result['error'], '#6533 : l\'approbation exige le rôle manager');
        $this->assertSame('pending', $absence->fresh()->status);
    }
}
