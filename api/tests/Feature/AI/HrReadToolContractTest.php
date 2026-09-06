<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\IntentEngine;
use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolDefinitionRegistry;
use App\AI\Support\AIToolSensitivity;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AIToolRegistrySeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B1 (#6854) — vérifie le contrat A3 côté BC-04 HR : les 3 outils lecture
 * sont déclarés au boot (HRServiceProvider → AIToolDefinitionRegistry), le
 * ToolRegistry les enrichit (sensitivity/bc/schémas), et l'exposition
 * `/ai/tools` respecte les rôles de la matrice ai.tool_permissions.
 */
class HrReadToolContractTest extends TestCase
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

    public function test_hr_read_tools_are_declared_at_boot_for_bc04(): void
    {
        foreach (['team_overview', 'team_absences_recent', 'employee_leave_balance'] as $name) {
            $definition = AIToolDefinitionRegistry::find($name);
            $this->assertInstanceOf(AIToolDefinition::class, $definition, "{$name} doit être déclarée au boot");
            $this->assertSame('BC-04', $definition->bc);
            $this->assertSame(AIToolSensitivity::Read, $definition->sensitivity);
            $this->assertNotEmpty($definition->permission);
            $this->assertNotEmpty($definition->inputSchema);
            $this->assertNotEmpty($definition->outputSchema);
        }
    }

    public function test_tool_registry_enriches_registry_entries(): void
    {
        $registry = app(ToolRegistry::class);
        $tool = $registry->findTool('team_overview');

        $this->assertNotNull($tool);
        $this->assertSame('read', $tool['sensitivity'] ?? null);
        $this->assertSame('BC-04', $tool['bc'] ?? null);
        $this->assertArrayHasKey('input_schema', $tool);
        $this->assertArrayHasKey('output_schema', $tool);
        $this->assertSame(1, $tool['tool_version'] ?? null);
        // Comportement existant préservé : la description du registre reste.
        $this->assertNotEmpty($tool['description']);
    }

    public function test_read_tools_have_intent_engine_handlers(): void
    {
        $engine = app(IntentEngine::class);
        $supported = $engine->supportedReadTools();

        foreach (['team_overview', 'team_absences_recent', 'employee_leave_balance'] as $name) {
            $this->assertContains($name, $supported, "{$name} doit avoir un handler IntentEngine");
        }
    }

    public function test_tools_endpoint_is_filtered_by_role(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);
        /** @var array<int, array<string, mixed>> $employeeData */
        $employeeData = $this->getJson('/api/v1/ai/tools')->json('data');
        $employeeNames = collect($employeeData)->pluck('name')->all();
        $this->assertContains('employee_leave_balance', $employeeNames);
        $this->assertNotContains('team_overview', $employeeNames);
        $this->assertNotContains('team_absences_recent', $employeeNames);

        /** @var Employee $manager */
        Sanctum::actingAs($manager);
        /** @var array<int, array<string, mixed>> $managerData */
        $managerData = $this->getJson('/api/v1/ai/tools')->json('data');
        $managerNames = collect($managerData)->pluck('name')->all();
        $this->assertContains('team_overview', $managerNames);
        $this->assertContains('team_absences_recent', $managerNames);
        $this->assertContains('employee_leave_balance', $managerNames);
    }
}
