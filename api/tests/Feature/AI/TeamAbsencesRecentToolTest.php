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
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B1 (#6854) — outil lecture `team_absences_recent` (contrat A3 #6850) :
 * autorisation, isolation tenant, période/statuts, shape non nominative.
 */
class TeamAbsencesRecentToolTest extends TestCase
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
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'team_absences_recent', $arguments)]),
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

    public function test_manager_sees_recent_team_absences_with_statuses(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $e1 = Employee::factory()->create(['company_id' => $company->id]);
        $e2 = Employee::factory()->create(['company_id' => $company->id]);
        $type = AbsenceType::factory()->create();

        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $e1->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'reason' => 'Motif prive',
        ]);
        // Hors fenêtre (40 jours) : exclue de la période par défaut (30 j).
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $e1->id,
            'absence_type_id' => $type->id,
            'status' => 'pending',
            'start_date' => now()->subDays(40)->toDateString(),
            'end_date' => now()->subDays(38)->toDateString(),
        ]);
        Absence::factory()->rejected()->create([
            'company_id' => $company->id,
            'employee_id' => $e2->id,
            'absence_type_id' => $type->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(9)->toDateString(),
        ]);
        // Employé archivé : ses absences sortent du périmètre.
        $archived = Employee::factory()->archived()->create(['company_id' => $company->id]);
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $archived->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDays(1)->toDateString(),
        ]);

        $result = $this->executeTool((string) $company->id, $manager->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame(2, $data['total'] ?? null);
        $this->assertSame(['approved' => 1, 'rejected' => 1], $data['by_status'] ?? null);
        $this->assertCount(2, $data['absences'] ?? []);

        $first = $data['absences'][0] ?? [];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('employee_id', $first);
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('start_date', $first);
        $this->assertArrayHasKey('end_date', $first);
        $this->assertArrayHasKey('days_count', $first);
        $this->assertArrayHasKey('status', $first);
        // Privacy A6 (#6853) : le motif (raison) n'est jamais exposé.
        $this->assertArrayNotHasKey('reason', $first);
    }

    public function test_status_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $type = AbsenceType::factory()->create();
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);
        Absence::factory()->rejected()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => now()->subDays(8)->toDateString(),
            'end_date' => now()->subDays(7)->toDateString(),
        ]);

        $result = $this->executeTool((string) $company->id, $manager->id, ['status' => 'approved']);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame(1, $data['total'] ?? null);
        $this->assertSame(['approved' => 1], $data['by_status'] ?? null);
    }

    public function test_custom_period_filters_absences(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $type = AbsenceType::factory()->create();
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(25)->toDateString(),
            'end_date' => now()->subDays(24)->toDateString(),
        ]);
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'status' => 'pending',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);

        $result = $this->executeTool(
            (string) $company->id,
            $manager->id,
            ['from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString()],
        );

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame(1, $data['total'] ?? null);
        $this->assertSame('pending', $data['absences'][0]['status'] ?? null);
    }

    public function test_supervisor_sees_only_own_team_absences(): void
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
        ]);
        $otherManager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $outsider = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $otherManager->id,
        ]);
        $type = AbsenceType::factory()->create();
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $report->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(4)->toDateString(),
        ]);
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $outsider->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);

        $result = $this->executeTool((string) $company->id, $supervisor->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame(1, $data['total'] ?? null);
        $this->assertSame($report->id, $data['absences'][0]['employee_id'] ?? null);
    }

    public function test_cross_tenant_absences_are_excluded(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
        $type = AbsenceType::factory()->create();
        Absence::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'absence_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDays(1)->toDateString(),
        ]);

        $result = $this->executeTool((string) $companyA->id, $managerA->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame(0, $data['total'] ?? null);
        $this->assertSame([], $data['absences'] ?? null);
    }

    public function test_employee_role_is_denied(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $result = $this->executeTool((string) $company->id, $employee->id);

        $this->assertFalse($result->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($result)['error'] ?? null);
    }
}
