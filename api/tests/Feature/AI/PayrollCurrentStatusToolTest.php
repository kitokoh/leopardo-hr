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
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B2 (#6855) — outil lecture `payroll_current_status` (contrat A3 #6850) :
 * autorisation (manager payroll.view, deny sinon), isolation tenant et shape
 * agrégée de sortie — statuts/dates/compteurs uniquement, jamais de montants
 * ni de données nominatives (privacy A6, #6853).
 */
class PayrollCurrentStatusToolTest extends TestCase
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
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'payroll_current_status', $arguments)]),
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

    private function run(Company $company, array $overrides = []): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::query()->create(array_merge([
            'company_id' => $company->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
            'employee_count' => 1,
        ], $overrides));

        return $run;
    }

    private function slip(PayrollRun $run, Company $company, int $employeeId, string $status = 'draft'): PaySlip
    {
        /** @var PaySlip $slip */
        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employeeId,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'status' => $status,
        ]);

        return $slip;
    }

    public function test_principal_gets_current_run_and_last_closed_run_aggregates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $company->id]);

        // Run clôturé (juillet) : 3 bulletins dont 2 validés.
        $closedRun = $this->run($company, [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_VALIDATED,
            'employee_count' => 3,
            'validated_at' => Carbon::parse('2026-08-01 10:00:00'),
        ]);
        $this->slip($closedRun, $company, $principal->id, 'validated');
        $this->slip($closedRun, $company, $employeeA->id, 'validated');
        $this->slip($closedRun, $company, $employeeB->id, 'draft');

        // Run en cours (août) : 5 salariés, 3 bulletins dont 1 validé.
        $currentRun = $this->run($company, ['employee_count' => 5]);
        $this->slip($currentRun, $company, $principal->id, 'validated');
        $this->slip($currentRun, $company, $employeeA->id, 'draft');
        $this->slip($currentRun, $company, $employeeB->id, 'draft');

        $result = $this->executeTool((string) $company->id, $principal->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertTrue($data['has_current_run'] ?? null);

        $current = $data['current_run'] ?? null;
        $this->assertIsArray($current);
        $this->assertSame($currentRun->id, $current['id']);
        $this->assertSame('draft', $current['status']);
        $this->assertSame(5, $current['employee_count']);
        $this->assertSame(3, $current['slips_count']);
        $this->assertSame(1, $current['validated_slips_count']);
        $this->assertSame(['start' => '2026-08-01', 'end' => '2026-08-31'], $current['period']);

        $closed = $data['last_closed_run'] ?? null;
        $this->assertIsArray($closed);
        $this->assertSame($closedRun->id, $closed['id']);
        $this->assertSame('validated', $closed['status']);
        $this->assertSame(3, $closed['employee_count']);
        $this->assertSame(3, $closed['slips_count']);
        $this->assertSame(2, $closed['validated_slips_count']);

        // Jamais de montants ni de données nominatives (privacy A6, #6853).
        $this->assertArrayNotHasKey('total_gross', $current);
        $this->assertArrayNotHasKey('total_net', $current);
        $this->assertArrayNotHasKey('gross_salary', $current);
        $this->assertArrayNotHasKey('slips', $current);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function test_no_current_run_when_only_closed_runs_exist(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $closedRun = $this->run($company, [
            'status' => PayrollRun::STATUS_PAID,
            'employee_count' => 2,
            'paid_at' => Carbon::parse('2026-08-02 09:00:00'),
        ]);

        $result = $this->executeTool((string) $company->id, $principal->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertFalse($data['has_current_run'] ?? null);
        $this->assertArrayHasKey('current_run', $data);
        $this->assertNull($data['current_run']);
        $this->assertSame('paid', $data['last_closed_run']['status'] ?? null);
        $this->assertSame($closedRun->id, $data['last_closed_run']['id'] ?? null);
    }

    public function test_empty_state_when_no_run_exists(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $result = $this->executeTool((string) $company->id, $principal->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertFalse($data['has_current_run'] ?? null);
        $this->assertArrayHasKey('current_run', $data);
        $this->assertNull($data['current_run']);
        $this->assertArrayHasKey('last_closed_run', $data);
        $this->assertNull($data['last_closed_run']);
    }

    public function test_cancelled_runs_are_ignored(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->run($company, ['status' => PayrollRun::STATUS_CANCELLED, 'employee_count' => 4]);

        $result = $this->executeTool((string) $company->id, $principal->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertFalse($data['has_current_run'] ?? null);
        $this->assertArrayHasKey('current_run', $data);
        $this->assertNull($data['current_run']);
        $this->assertArrayHasKey('last_closed_run', $data);
        $this->assertNull($data['last_closed_run']);
    }

    public function test_employee_role_is_denied(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $result = $this->executeTool((string) $company->id, $employee->id);

        $this->assertFalse($result->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($result)['error'] ?? null);
    }

    public function test_cross_tenant_runs_are_never_exposed(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);

        $this->run($companyA, ['employee_count' => 2]);
        $this->run($companyB, ['employee_count' => 9]);
        $this->run($companyB, ['status' => PayrollRun::STATUS_VALIDATED, 'employee_count' => 7]);

        $result = $this->executeTool((string) $companyA->id, $managerA->id);

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertTrue($data['has_current_run'] ?? null);
        $this->assertSame(2, $data['current_run']['employee_count'] ?? null);
        $this->assertArrayHasKey('last_closed_run', $data);
        $this->assertNull($data['last_closed_run']);
    }
}
