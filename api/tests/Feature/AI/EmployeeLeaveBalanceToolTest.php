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
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B1 (#6854) — outil lecture `employee_leave_balance` (contrat A3 #6850) :
 * autorisation (self vs manager), isolation tenant, shape du snapshot
 * canonique LeaveBalance (propriétaire Planning, PA2-ARCH-002).
 */
class EmployeeLeaveBalanceToolTest extends TestCase
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
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'employee_leave_balance', $arguments)]),
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

    public function test_employee_reads_own_leave_balance(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $type = AbsenceType::factory()->create(['name' => 'Conges annuels']);
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 20.0,
            'used' => 3.0,
            'pending' => 2.0,
            'year' => (int) now()->format('Y'),
        ]);

        $result = $this->executeTool((string) $company->id, $employee->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame($employee->id, $data['employee_id'] ?? null);
        $this->assertSame((int) now()->format('Y'), $data['year'] ?? null);
        $this->assertSame(1, $data['count'] ?? null);
        $balance = $data['leave_balances'][0] ?? [];
        $this->assertSame('Conges annuels', $balance['absence_type'] ?? null);
        $this->assertSame(20.0, $balance['balance'] ?? null);
        $this->assertSame(3.0, $balance['used'] ?? null);
        $this->assertSame(2.0, $balance['pending'] ?? null);
    }

    public function test_employee_cannot_read_another_employee_balance(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $other = Employee::factory()->create(['company_id' => $company->id]);

        $result = $this->executeTool((string) $company->id, $employee->id, ['employee_id' => $other->id]);

        $this->assertTrue($result->success); // fail doux type « not found » (#6532)
        $this->assertSame('Employee not found', $this->payload($result)['error'] ?? null);
    }

    public function test_manager_reads_any_company_employee_balance(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $type = AbsenceType::factory()->create();
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 12.0,
            'used' => 0.0,
            'pending' => 0.0,
            'year' => (int) now()->format('Y'),
        ]);

        $result = $this->executeTool((string) $company->id, $manager->id, ['employee_id' => $employee->id]);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame($employee->id, $data['employee_id'] ?? null);
        $this->assertSame(1, $data['count'] ?? null);
    }

    public function test_manager_cannot_read_cross_tenant_employee(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        $result = $this->executeTool((string) $companyA->id, $managerA->id, ['employee_id' => $employeeB->id]);

        $this->assertTrue($result->success);
        $this->assertSame('Employee not found', $this->payload($result)['error'] ?? null);
    }

    public function test_year_argument_selects_snapshot(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $type = AbsenceType::factory()->create();
        $currentYear = (int) now()->format('Y');
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 5.0,
            'used' => 1.0,
            'pending' => 0.0,
            'year' => $currentYear - 1,
        ]);

        $result = $this->executeTool((string) $company->id, $employee->id, ['year' => $currentYear - 1]);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame($currentYear - 1, $data['year'] ?? null);
        $this->assertSame(1, $data['count'] ?? null);
    }

    public function test_employee_role_is_allowed_for_self(): void
    {
        // employee_leave_balance est accessible à l'employé pour SON solde
        // (rôle employee, permission leave.view — parité get_leave_balances).
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $result = $this->executeTool((string) $company->id, $employee->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertSame($employee->id, $data['employee_id'] ?? null);
        $this->assertSame([], $data['leave_balances'] ?? null);
    }
}
