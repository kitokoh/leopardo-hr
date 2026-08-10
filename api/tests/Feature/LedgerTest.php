<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\LedgerEntry;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\LedgerService;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-PAY-007 — Ledger financier employee.
 *
 * Every advance and payment must be recorded as an immutable, auditable
 * journal entry with a correctly computed running balance.
 */
class LedgerTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_ledger_service_records_entry_with_running_balance(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company, 'employee');

        $service = app(LedgerService::class);

        $first = $service->record(
            employee: $employee,
            entryType: LedgerEntry::TYPE_ADVANCE,
            amount: -500,
            description: 'Advance payout',
        );

        $this->assertSame(-500.0, $first->amount);
        $this->assertSame(-500.0, $first->balance_after);

        $second = $service->record(
            employee: $employee,
            entryType: LedgerEntry::TYPE_PAYMENT,
            amount: 2000,
            description: 'Monthly salary payment',
        );

        $this->assertSame(2000.0, $second->amount);
        $this->assertSame(1500.0, $second->balance_after);

        $this->assertSame(1500.0, $service->currentBalance($employee));
    }

    public function test_ledger_history_is_scoped_to_employee_and_company(): void
    {
        $company = $this->createCompany();
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        $service = app(LedgerService::class);
        $service->record($employee1, LedgerEntry::TYPE_ADVANCE, -300);
        $service->record($employee2, LedgerEntry::TYPE_ADVANCE, -100);

        $history = $service->history($employee1);

        $this->assertCount(1, $history->items());
        $this->assertSame($employee1->id, LedgerEntry::query()->first()->employee_id ?? null);
    }

    public function test_marking_advance_paid_creates_a_ledger_entry(): void
    {
        $company = $this->createCompany();
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 400,
            'status' => 'approved',
            'validation_status' => 'manager_approved',
            'manager_approved_by' => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/mark-paid", [
                'payment_reference' => 'CASH-2026-777',
            ])
            ->assertOk();

        $this->assertDatabaseHas('ledger_entries', [
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'entry_type' => LedgerEntry::TYPE_ADVANCE,
            'amount' => -400,
            'balance_after' => -400,
            'source_type' => (new SalaryAdvance)->getMorphClass(),
            'source_id' => $advance->id,
        ]);
    }

    public function test_employee_can_read_own_ledger_history(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company, 'employee');

        app(LedgerService::class)->record($employee, LedgerEntry::TYPE_PAYMENT, 1500, 'Salary');

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/me/ledger');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.entry_type', LedgerEntry::TYPE_PAYMENT);
        $response->assertJsonPath('data.0.amount', 1500);
        $response->assertJsonPath('meta.current_balance', 1500);
    }

    public function test_employee_cannot_read_another_employees_ledger(): void
    {
        $company = $this->createCompany();
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        app(LedgerService::class)->record($employee2, LedgerEntry::TYPE_PAYMENT, 1000);

        $this->actingAs($employee1, 'sanctum')
            ->getJson("/api/v1/employees/{$employee2->id}/ledger")
            ->assertStatus(403);
    }

    public function test_manager_can_read_employee_ledger_within_company(): void
    {
        $company = $this->createCompany();
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        app(LedgerService::class)->record($employee, LedgerEntry::TYPE_PAYMENT, 900);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/ledger")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_cannot_read_ledger_of_employee_in_another_company(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        app(LedgerService::class)->record($employeeB, LedgerEntry::TYPE_PAYMENT, 500);

        $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/employees/{$employeeB->id}/ledger")
            ->assertStatus(404);
    }

    /** F-13 (#1543/#1569) : factories sur les vraies migrations (ex-CreatesMvpSchema). */
    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Ledger Test Co',
            'slug' => 'ledger-'.Str::random(6),
            'sector' => 'test',
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);

        return $company;
    }

    private function createEmployee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'status' => 'active',
        ]);

        return $employee;
    }
}
