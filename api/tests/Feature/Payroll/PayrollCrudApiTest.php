<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\Payroll;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * S-4 (#1664) — couverture des contrôleurs CRUD paie (PayrollController)
 * et des requêtes associées (index filtré, store, update, validate).
 * Complète la couverture du module Payroll pour le gate ≥ 80 % (F-14).
 */
class PayrollCrudApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    private function actingAsManager(): void
    {
        Sanctum::actingAs($this->manager);
    }

    private function createPayroll(): Payroll
    {
        return (new \App\Modules\Payroll\Infrastructure\Services\PayrollService())->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'overtime_amount' => 5000,
            'bonuses' => [['label' => 'Prime', 'amount' => 2000]],
            'deductions' => [['label' => 'Retenue', 'amount' => 1000]],
            'cotisations' => [['label' => 'CNAS', 'amount' => 5400]],
            'ir_amount' => 7042,
            'advance_deduction' => 3000,
        ]);
    }

    public function test_index_with_filters_returns_payrolls(): void
    {
        $this->actingAsManager();
        $this->createPayroll();

        $this->getJson('/api/v1/payrolls?period_year=2026&period_month=7')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/payrolls', ['employee_id' => $this->employee->id])
            ->assertStatus(422);
    }

    public function test_store_creates_payroll(): void
    {
        $this->actingAsManager();

        $response = $this->postJson('/api/v1/payrolls', [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'overtime_amount' => 5000,
        ])->assertCreated()->assertJsonPath('data.employee_id', (int) $this->employee->id);

        $this->getJson("/api/v1/payrolls/{$response->json('data.id')}")->assertOk();
    }

    public function test_validate_and_destroy_flow(): void
    {
        $this->actingAsManager();
        $payroll = $this->createPayroll();

        // Validation → 200.
        $this->putJson("/api/v1/payrolls/{$payroll->id}/validate")->assertOk();

        // Une fiche validée ne peut plus être supprimée (règle métier).
        $this->deleteJson("/api/v1/payrolls/{$payroll->id}")->assertStatus(422);

        // Une fiche non validée se supprime (période différente).
        $draft = (new \App\Modules\Payroll\Infrastructure\Services\PayrollService())->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 8,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'overtime_amount' => 0,
        ]);
        $this->deleteJson("/api/v1/payrolls/{$draft->id}")->assertOk();
        $this->assertNull(Payroll::query()->find($draft->id));
        $this->assertNotNull(Payroll::query()->find($payroll->id));
    }
}
