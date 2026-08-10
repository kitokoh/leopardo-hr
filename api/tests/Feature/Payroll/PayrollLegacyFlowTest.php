<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\PayrollAlreadyValidatedException;
use App\Exceptions\PayrollPeriodConflictException;
use App\Modules\Payroll\Domain\Models\Payroll;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\PayrollService;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-4 (#1664) — Couverture Payroll ≥ 80 % : `PayrollService` +
 * `PayrollController` (flux paie legacy `/payrolls`, F-14).
 */
class PayrollLegacyFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    private function service(): PayrollService
    {
        return new PayrollService();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'overtime_amount' => 1500,
            'bonuses' => [['label' => 'Prime', 'amount' => 5000]],
            'deductions' => [['label' => 'Avance', 'amount' => 2000]],
            'cotisations' => [['label' => 'CNAS', 'amount' => 5400]],
            'ir_amount' => 1000,
            'advance_deduction' => 3000,
            'absence_deduction' => 0,
            'penalty_deduction' => 0,
        ], $overrides);
    }

    public function test_service_create_and_period_conflict(): void
    {
        $payroll = $this->service()->create($this->manager, $this->payload());
        $this->assertSame('draft', $payroll->status);
        $this->assertSame(60000, (int) $payroll->gross_salary);
        $this->assertSame($this->company->id, $payroll->company_id);

        // Doublon même mois/année/employé → conflit de période.
        $this->expectException(PayrollPeriodConflictException::class);
        $this->service()->create($this->manager, $this->payload());
    }

    public function test_service_update_recomputes_net_and_rejects_validated(): void
    {
        $payroll = $this->service()->create($this->manager, $this->payload());

        $updated = $this->service()->update($payroll, $this->payload(['gross_salary' => 70000, 'bonuses' => []]));

        $this->assertSame(70000, (int) $updated->gross_salary);
        $this->assertGreaterThan(0, (int) $updated->net_salary);

        $this->service()->validate($updated, $this->manager);

        $this->expectException(PayrollAlreadyValidatedException::class);
        $this->service()->update($updated->fresh(), $this->payload());
    }

    public function test_service_validate_applies_advance_deduction(): void
    {
        /** @var SalaryAdvance $advance */
        $advance = SalaryAdvance::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000,
            'amount_remaining' => 4000,
            'status' => 'active',
        ]);

        $payroll = $this->service()->create($this->manager, $this->payload(['advance_deduction' => 3000]));

        Event::fake([\App\Events\PayrollValidated::class]);

        $validated = $this->service()->validate($payroll, $this->manager);

        $this->assertSame('validated', $validated->status);
        $this->assertSame($this->manager->id, $validated->validated_by);
        $this->assertSame(1000.0, $advance->fresh()->amount_remaining);
        Event::assertDispatched(\App\Events\PayrollValidated::class);
    }

    public function test_service_validate_rejects_double_validation_and_delete_validated(): void
    {
        $payroll = $this->service()->create($this->manager, $this->payload());
        $this->service()->validate($payroll, $this->manager);

        $this->expectException(PayrollAlreadyValidatedException::class);
        $this->service()->validate($payroll, $this->manager);
    }

    public function test_controller_crud_and_rbac(): void
    {
        Sanctum::actingAs($this->manager);

        // Création.
        $create = $this->postJson('/api/v1/payrolls', $this->payload())->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertSame('draft', $create->json('data.status'));

        // Index (filtre status).
        $this->getJson('/api/v1/payrolls?status=draft')->assertOk()
            ->assertJsonFragment(['id' => $id]);

        // Show.
        $this->getJson("/api/v1/payrolls/{$id}")->assertOk()
            ->assertJsonPath('data.employee_id', $this->employee->id);

        // Update.
        $this->putJson("/api/v1/payrolls/{$id}", $this->payload(['gross_salary' => 65000]))
            ->assertOk()
            ->assertJsonPath('data.gross_salary', 65000);

        // Validate.
        $this->putJson("/api/v1/payrolls/{$id}/validate")->assertOk()
            ->assertJsonPath('data.status', 'validated');

        // Destroy (draft impossible après validation → validation refusée sur delete validé).
        $this->deleteJson("/api/v1/payrolls/{$id}")->assertStatus(422);
    }

    public function test_controller_employee_cannot_manage_payrolls(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/payrolls', $this->payload())->assertStatus(403);

        // Un employé peut lister ses propres bulletins via index (scope employee).
        $this->getJson('/api/v1/payrolls')->assertOk();
    }

    public function test_controller_cross_tenant_404(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create();
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);

        /** @var Payroll $payroll */
        $payroll = $this->service()->create($this->manager, $this->payload());

        Sanctum::actingAs($otherManager);

        $this->getJson("/api/v1/payrolls/{$payroll->id}")->assertStatus(404);
        $this->putJson("/api/v1/payrolls/{$payroll->id}", $this->payload())->assertStatus(404);
        $this->deleteJson("/api/v1/payrolls/{$payroll->id}")->assertStatus(404);
    }
}
