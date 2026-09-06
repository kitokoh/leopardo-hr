<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\AI\IntentEngine;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B1 (#6854) — outil lecture `team_overview` (contrat A3 #6850) : autorisation,
 * isolation tenant, shape agrégée de sortie (jamais de données nominatives).
 */
class TeamOverviewToolTest extends TestCase
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
     * @param  array<string, mixed>  $arguments
     */
    private function executeTool(string $companyId, int $userId, array $arguments = []): ToolResult
    {
        $engine = app(IntentEngine::class);

        return $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'team_overview', $arguments)]),
            $companyId,
            $userId,
        )[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ToolResult $result): array
    {
        $decoded = json_decode($result->content, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function test_principal_manager_gets_company_aggregates(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'contract_type' => 'CDI',
        ]);
        $deptA = Department::create(['name' => 'Operations']);
        $deptB = Department::create(['name' => 'Comptabilite']);

        Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $deptA->id,
            'contract_type' => 'CDI',
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $deptA->id,
            'contract_type' => 'CDD',
            'status' => 'suspended',
        ]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $deptB->id,
            'contract_type' => 'Stage',
            'status' => 'active',
        ]);
        // Archivé : exclu de l'effectif (parité HrController::teamOverview).
        Employee::factory()->archived()->create([
            'company_id' => $company->id,
            'department_id' => $deptA->id,
        ]);

        $result = $this->executeTool((string) $company->id, $principal->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame('company', $data['scope'] ?? null);
        $this->assertSame(4, $data['total'] ?? null);
        $this->assertSame(['active' => 3, 'suspended' => 1], $data['by_status'] ?? null);
        $this->assertSame(['CDI' => 2, 'CDD' => 1, 'Stage' => 1], $data['by_contract_type'] ?? null);
        // Jamais de liste nominative (privacy A6, #6853) : agrégats uniquement.
        $this->assertArrayNotHasKey('employees', $data);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function test_supervisor_scope_is_limited_to_direct_team(): void
    {
        $company = Company::factory()->create();
        $supervisor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'superviseur',
        ]);
        $report = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $supervisor->id,
            'status' => 'active',
        ]);
        // Autre employé, rattaché à un autre manager : hors périmètre.
        $otherManager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $otherManager->id,
            'status' => 'active',
        ]);
        // Report archivé : exclu de l'effectif.
        Employee::factory()->archived()->create([
            'company_id' => $company->id,
            'manager_id' => $supervisor->id,
        ]);

        $result = $this->executeTool((string) $company->id, $supervisor->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame('team', $data['scope'] ?? null);
        $this->assertSame(2, $data['total'] ?? null); // superviseur + report direct
    }

    public function test_employee_role_is_denied(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $result = $this->executeTool((string) $company->id, $employee->id);

        $this->assertFalse($result->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($result)['error'] ?? null);
    }

    public function test_cross_tenant_employees_are_never_counted(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $deptA = Department::create(['name' => 'Equipe A']);
        $deptB = Department::create(['name' => 'Equipe B']);

        Employee::factory()->create(['company_id' => $companyA->id, 'department_id' => $deptA->id]);
        Employee::factory()->count(3)->create(['company_id' => $companyB->id, 'department_id' => $deptB->id]);

        $result = $this->executeTool((string) $companyA->id, $managerA->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame(2, $data['total'] ?? null); // managerA + 1 employé A
        $departments = array_column($data['by_department'] ?? [], 'department');
        $this->assertContains('Equipe A', $departments);
        $this->assertNotContains('Equipe B', $departments);
    }
}
